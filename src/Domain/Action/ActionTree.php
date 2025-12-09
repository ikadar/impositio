<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathPipeline;
use App\Domain\Action\Pipeline\ExtensionParams;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\MachineType;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Builds and processes action trees for production planning.
 *
 * The ActionTree is responsible for:
 * 1. Building a tree of possible machine assignments (calculateTree/calculate)
 * 2. Flattening the tree into linear action paths (flattenTree/flatten)
 * 3. Extending paths with additional actions like CTP, cutting (extend)
 *
 * Action paths are returned in "backtrace order" - the last production step first.
 *
 * This class now delegates to specialized classes:
 * - ActionTreeBuilder: Tree construction
 * - ActionTreeFlattener: Tree flattening
 * - ActionTreeProcessor: Workflow orchestration
 */
class ActionTree implements ActionTreeInterface
{
    protected array $root = [];

    protected DimensionsInterface $openPoseDimensions;
    protected DimensionsInterface $closedPoseDimensions;
    protected float $numberOfCopies;
    protected float $numberOfColors;
    protected float $paperWeight;
    protected array $inking = [];

    private ActionTreeBuilder $builder;
    private ActionTreeFlattener $flattener;
    private ActionTreeProcessor $processor;

    public function __construct(
        protected Calculator $layoutCalculator,
        protected EquipmentFactoryInterface $equipmentFactory,
        protected PropertyAccessorInterface $propertyAccessor,
        protected ?ActionPathPipeline $pipeline = null,
    ) {
        $this->builder = new ActionTreeBuilder($layoutCalculator, $propertyAccessor);
        $this->flattener = new ActionTreeFlattener();
        $this->processor = new ActionTreeProcessor(
            $this->builder,
            $this->flattener,
            $pipeline,
            $equipmentFactory,
            $propertyAccessor
        );
    }

    public function getRoot(): array
    {
        return $this->root;
    }

    public function setRoot(array $root): static
    {
        $this->root = $root;
        return $this;
    }

    public function getOpenPoseDimensions(): DimensionsInterface
    {
        return $this->openPoseDimensions;
    }

    public function setOpenPoseDimensions(DimensionsInterface $openPoseDimensions): static
    {
        $this->openPoseDimensions = $openPoseDimensions;
        return $this;
    }

    public function getClosedPoseDimensions(): DimensionsInterface
    {
        return $this->closedPoseDimensions;
    }

    public function setClosedPoseDimensions(DimensionsInterface $closedPoseDimensions): static
    {
        $this->closedPoseDimensions = $closedPoseDimensions;
        return $this;
    }

    public function getNumberOfCopies(): float
    {
        return $this->numberOfCopies;
    }

    public function setNumberOfCopies(float $numberOfCopies): static
    {
        $this->numberOfCopies = $numberOfCopies;
        return $this;
    }

    public function getNumberOfColors(): float
    {
        return $this->numberOfColors;
    }

    public function setNumberOfColors(float $numberOfColors): static
    {
        $this->numberOfColors = $numberOfColors;
        return $this;
    }

    public function getPaperWeight(): float
    {
        return $this->paperWeight;
    }

    public function setPaperWeight(float $paperWeight): static
    {
        $this->paperWeight = $paperWeight;
        return $this;
    }

    public function getInking(): array
    {
        return $this->inking;
    }

    public function setInking(array $inking): static
    {
        $this->inking = $inking;
        return $this;
    }

    /**
     * Build the action tree for a given set of abstract actions.
     *
     * @param AbstractActionInterface[] $abstractActions Actions to process
     * @param PressSheetInterface $pressSheet The press sheet to use
     * @param InputSheetInterface $zone The zone/input sheet
     * @param array $prevNodes Previous nodes (unused, kept for compatibility)
     * @param array $inking Inking specification ['recto' => [...], 'verso' => [...]]
     * @return ActionTreeNodeInterface[] Root nodes of the built tree
     */
    public function calculateTree(
        array $abstractActions,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        array $prevNodes = [],
        array $inking = [],
    ): array {
        $context = $this->createBuildContext();
        $this->setRoot($this->builder->buildTree($abstractActions, $pressSheet, $zone, $inking, $context));
        return $this->getRoot();
    }

    /**
     * Recursively build the action tree.
     *
     * @deprecated Use ActionTreeBuilder::buildTree() instead
     */
    protected function calculate(
        array $abstractActions,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        array $prevNodes = [],
        array $inking = [],
    ): array {
        $context = $this->createBuildContext();
        return $this->builder->buildTree($abstractActions, $pressSheet, $zone, $inking, $context);
    }

    /**
     * Flatten the action tree into linear action paths.
     *
     * Converts the tree structure into an array of paths, where each path
     * is a linear sequence of actions. Paths are returned in "backtrace order"
     * (last production step first).
     *
     * @return ActionTreeNodeInterface[][] Array of action paths
     */
    public function flattenTree(): array
    {
        return $this->flattener->flattenTree($this->getRoot());
    }

    /**
     * Recursively flatten a tree node into paths (forward order).
     *
     * @deprecated Use ActionTreeFlattener::flattenTree() instead
     */
    protected function flatten(ActionTreeNodeInterface $node, array $path = []): array
    {
        // Delegate to flattener's flatten method which returns forward-order paths
        return $this->flattener->flatten($node, $path);
    }

    /**
     * Process abstract actions and return extended action paths.
     *
     * This is the main entry point for production planning. It:
     * 1. Sets up internal state from parameters
     * 2. For each press sheet: builds tree, flattens, extends
     * 3. Returns all extended action paths
     *
     * @param AbstractActionInterface[] $abstractActions Actions to process
     * @param PressSheetInterface[] $pressSheets Available press sheets
     * @param InputSheetInterface $zone The zone/input sheet
     * @param DimensionsInterface $openPoseDimensions Open pose dimensions
     * @param DimensionsInterface $closedPoseDimensions Closed pose dimensions
     * @param float $numberOfCopies Number of copies to produce
     * @param float $numberOfColors Number of colors
     * @param float $paperWeight Paper weight in g/m²
     * @param array $inking Inking specification
     * @return array Extended action paths
     */
    public function process(
        array $abstractActions,
        array $pressSheets,
        InputSheetInterface $zone,
        DimensionsInterface $openPoseDimensions,
        DimensionsInterface $closedPoseDimensions,
        float $numberOfCopies,
        float $numberOfColors,
        float $paperWeight,
        array $inking,
    ): array {
        // Set state for backward compatibility
        $this->setOpenPoseDimensions($openPoseDimensions);
        $this->setClosedPoseDimensions($closedPoseDimensions);
        $this->setNumberOfCopies($numberOfCopies);
        $this->setNumberOfColors($numberOfColors);
        $this->setPaperWeight($paperWeight);
        $this->setInking($inking);

        $context = new TreeBuildContext(
            $openPoseDimensions,
            $closedPoseDimensions,
            $numberOfCopies,
            $numberOfColors,
            $paperWeight,
            $inking
        );

        return $this->processor->process($abstractActions, $pressSheets, $zone, $context);
    }

    /**
     * Extend a flat action path with additional actions (CTP, cutting, verso).
     *
     * Uses the pipeline if available, otherwise falls back to legacy implementation.
     *
     * @param ActionTreeNodeInterface[] $flatActionPath The flattened action path
     * @return ActionPathNode[] Extended action path with inserted actions
     */
    public function extend(array $flatActionPath): array
    {
        $context = $this->createBuildContext();
        return $this->processor->extend($flatActionPath, $context);
    }

    /**
     * Legacy extend implementation.
     *
     * @deprecated Will be removed in Phase 6. Use pipeline-based extend() instead.
     */
    public function extendLegacy(array $flatActionPath): array
    {
        $context = $this->createBuildContext();
        return $this->processor->extendLegacy($flatActionPath, $context);
    }

    /**
     * Create a TreeBuildContext from current state.
     */
    private function createBuildContext(): TreeBuildContext
    {
        return new TreeBuildContext(
            $this->openPoseDimensions,
            $this->closedPoseDimensions,
            $this->numberOfCopies,
            $this->numberOfColors,
            $this->paperWeight,
            $this->inking
        );
    }
}
