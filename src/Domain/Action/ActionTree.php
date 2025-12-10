<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Action\Pipeline\ActionPathPipeline;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Part\PartProductionContext;
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
 * This class delegates to specialized classes:
 * - ActionTreeBuilder: Tree construction
 * - ActionTreeFlattener: Tree flattening
 * - ActionTreeProcessor: Workflow orchestration
 *
 * Phase 6: Legacy code removed. Pipeline is now required.
 */
class ActionTree implements ActionTreeInterface
{
    protected array $root = [];

    /**
     * Current build context (set by process() or setters for backward compatibility).
     */
    private ?PartProductionContext $context = null;

    private ActionTreeBuilder $builder;
    private ActionTreeFlattener $flattener;
    private ActionTreeProcessor $processor;

    public function __construct(
        protected Calculator $layoutCalculator,
        protected PropertyAccessorInterface $propertyAccessor,
        protected ActionPathPipeline $pipeline,
    ) {
        $this->builder = new ActionTreeBuilder($layoutCalculator, $propertyAccessor);
        $this->flattener = new ActionTreeFlattener();
        $this->processor = new ActionTreeProcessor(
            $this->builder,
            $this->flattener,
            $pipeline,
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

    /**
     * Get the open pose dimensions.
     *
     * @deprecated Access via PartProductionContext instead
     */
    public function getOpenPoseDimensions(): DimensionsInterface
    {
        return $this->context->getOpenPoseDimensions();
    }

    /**
     * Set the open pose dimensions.
     *
     * @deprecated Use process() method parameters instead
     */
    public function setOpenPoseDimensions(DimensionsInterface $openPoseDimensions): static
    {
        $this->context = $this->createUpdatedContext(openPoseDimensions: $openPoseDimensions);
        return $this;
    }

    /**
     * Get the closed pose dimensions.
     *
     * @deprecated Access via PartProductionContext instead
     */
    public function getClosedPoseDimensions(): DimensionsInterface
    {
        return $this->context->getClosedPoseDimensions();
    }

    /**
     * Set the closed pose dimensions.
     *
     * @deprecated Use process() method parameters instead
     */
    public function setClosedPoseDimensions(DimensionsInterface $closedPoseDimensions): static
    {
        $this->context = $this->createUpdatedContext(closedPoseDimensions: $closedPoseDimensions);
        return $this;
    }

    /**
     * Get the number of copies.
     *
     * @deprecated Access via PartProductionContext instead
     */
    public function getNumberOfCopies(): float
    {
        return $this->context->getNumberOfCopies();
    }

    /**
     * Set the number of copies.
     *
     * @deprecated Use process() method parameters instead
     */
    public function setNumberOfCopies(float $numberOfCopies): static
    {
        $this->context = $this->createUpdatedContext(numberOfCopies: $numberOfCopies);
        return $this;
    }

    /**
     * Get the number of colors.
     *
     * @deprecated Access via PartProductionContext instead
     */
    public function getNumberOfColors(): float
    {
        return $this->context->getNumberOfColors();
    }

    /**
     * Set the number of colors.
     *
     * @deprecated Use process() method parameters instead
     */
    public function setNumberOfColors(float $numberOfColors): static
    {
        $this->context = $this->createUpdatedContext(numberOfColors: $numberOfColors);
        return $this;
    }

    /**
     * Get the paper weight.
     *
     * @deprecated Access via PartProductionContext instead
     */
    public function getPaperWeight(): float
    {
        return $this->context->getPaperWeight();
    }

    /**
     * Set the paper weight.
     *
     * @deprecated Use process() method parameters instead
     */
    public function setPaperWeight(float $paperWeight): static
    {
        $this->context = $this->createUpdatedContext(paperWeight: $paperWeight);
        return $this;
    }

    /**
     * Get the inking specification.
     *
     * @deprecated Access via PartProductionContext instead
     */
    public function getInking(): array
    {
        return $this->context?->getInking() ?? [];
    }

    /**
     * Set the inking specification.
     *
     * @deprecated Use process() method parameters instead
     */
    public function setInking(array $inking): static
    {
        $this->context = $this->createUpdatedContext(inking: $inking);
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
        $this->setRoot($this->builder->buildTree($abstractActions, $pressSheet, $zone, $inking, $this->context));
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
        return $this->builder->buildTree($abstractActions, $pressSheet, $zone, $inking, $this->context);
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
        return $this->flattener->flatten($node, $path);
    }

    /**
     * Process abstract actions and return extended action paths.
     *
     * This is the main entry point for production planning. It:
     * 1. For each press sheet: builds tree, flattens, extends
     * 2. Returns all extended action paths
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
        // Create immutable context from parameters
        $this->context = new PartProductionContext(
            numberOfCopies: $numberOfCopies,
            numberOfColors: $numberOfColors,
            paperWeight: $paperWeight,
            inking: $inking,
            openPoseDimensions: $openPoseDimensions,
            closedPoseDimensions: $closedPoseDimensions,
        );

        return $this->processor->process($abstractActions, $pressSheets, $zone, $this->context);
    }

    /**
     * Extend a flat action path with additional actions (CTP, cutting, verso).
     *
     * Uses the pipeline architecture for path extension.
     *
     * @param ActionTreeNodeInterface[] $flatActionPath The flattened action path
     * @return ActionPathNode[] Extended action path with inserted actions
     */
    public function extend(array $flatActionPath): array
    {
        return $this->processor->extend($flatActionPath, $this->context);
    }

    /**
     * Create an updated context with optional overrides.
     *
     * Used by deprecated setters for backward compatibility.
     */
    private function createUpdatedContext(
        ?DimensionsInterface $openPoseDimensions = null,
        ?DimensionsInterface $closedPoseDimensions = null,
        ?float $numberOfCopies = null,
        ?float $numberOfColors = null,
        ?float $paperWeight = null,
        ?array $inking = null,
    ): PartProductionContext {
        // If no context exists, create a minimal one with the provided value
        if ($this->context === null) {
            // Create placeholder dimensions if needed
            $defaultDimensions = new \App\Domain\Geometry\Dimensions(0, 0);
            return new PartProductionContext(
                numberOfCopies: $numberOfCopies ?? 0,
                numberOfColors: $numberOfColors ?? 0,
                paperWeight: $paperWeight ?? 0,
                inking: $inking ?? [],
                openPoseDimensions: $openPoseDimensions ?? $defaultDimensions,
                closedPoseDimensions: $closedPoseDimensions ?? $defaultDimensions,
            );
        }

        return new PartProductionContext(
            numberOfCopies: $numberOfCopies ?? $this->context->getNumberOfCopies(),
            numberOfColors: $numberOfColors ?? $this->context->getNumberOfColors(),
            paperWeight: $paperWeight ?? $this->context->getPaperWeight(),
            inking: $inking ?? $this->context->getInking(),
            openPoseDimensions: $openPoseDimensions ?? $this->context->getOpenPoseDimensions(),
            closedPoseDimensions: $closedPoseDimensions ?? $this->context->getClosedPoseDimensions(),
        );
    }
}
