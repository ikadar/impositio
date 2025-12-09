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

    public function __construct(
        protected Calculator $layoutCalculator,
        protected EquipmentFactoryInterface $equipmentFactory,
        protected PropertyAccessorInterface $propertyAccessor,
        protected ?ActionPathPipeline $pipeline = null,
    ) {}

    public function getRoot(): array
    {
        return $this->root;
    }

    public function setRoot(array $root): ActionTree
    {
        $this->root = $root;
        return $this;
    }

    public function getOpenPoseDimensions(): DimensionsInterface
    {
        return $this->openPoseDimensions;
    }

    public function setOpenPoseDimensions(DimensionsInterface $openPoseDimensions): ActionTree
    {
        $this->openPoseDimensions = $openPoseDimensions;
        return $this;
    }

    public function getClosedPoseDimensions(): DimensionsInterface
    {
        return $this->closedPoseDimensions;
    }

    public function setClosedPoseDimensions(DimensionsInterface $closedPoseDimensions): ActionTree
    {
        $this->closedPoseDimensions = $closedPoseDimensions;
        return $this;
    }

    public function getNumberOfCopies(): float
    {
        return $this->numberOfCopies;
    }

    public function setNumberOfCopies(float $numberOfCopies): ActionTree
    {
        $this->numberOfCopies = $numberOfCopies;
        return $this;
    }

    public function getNumberOfColors(): float
    {
        return $this->numberOfColors;
    }

    public function setNumberOfColors(float $numberOfColors): ActionTree
    {
        $this->numberOfColors = $numberOfColors;
        return $this;
    }

    public function getPaperWeight(): float
    {
        return $this->paperWeight;
    }

    public function setPaperWeight(float $paperWeight): ActionTree
    {
        $this->paperWeight = $paperWeight;
        return $this;
    }

    public function getInking(): array
    {
        return $this->inking;
    }

    public function setInking(array $inking): ActionTree
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
        $this->setRoot($this->calculate($abstractActions, $pressSheet, $zone, $prevNodes, $inking));
        return $this->getRoot();
    }

    /**
     * Recursively build the action tree.
     *
     * For each abstract action, finds capable machines and creates ActionTreeNodes
     * for each machine/gridFitting combination. Recursively processes remaining actions.
     *
     * @param AbstractActionInterface[] $abstractActions Remaining actions to process
     * @param PressSheetInterface $pressSheet The press sheet
     * @param InputSheetInterface $zone Current zone (may change through recursion)
     * @param array $prevNodes Previous nodes (unused)
     * @param array $inking Inking specification
     * @return ActionTreeNodeInterface[] Tree nodes for this level
     */
    protected function calculate(
        array $abstractActions,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        array $prevNodes = [],
        array $inking = [],
    ): array {
        /** @var AbstractActionInterface|null $abstractAction */
        $abstractAction = array_shift($abstractActions);

        if ($abstractAction === null) {
            return $prevNodes;
        }

        $availableMachines = $abstractAction->getAvailableMachines();

        // Filter printing presses by color capability
        if ($abstractAction->getMachineType() === MachineType::PrintingPress) {
            $availableMachines = $this->filterMachinesByColorCapability($availableMachines, $inking);
        }

        $actionPaths = [];

        foreach ($availableMachines as $machine) {
            $action = new Action(
                $machine,
                $pressSheet,
                $zone,
                $this->layoutCalculator
            );

            // Special handling for folder machines
            if ($action->getMachine()->getType() === MachineType::Folder) {
                $zone->setDimensions($this->getOpenPoseDimensions());
                $machine->setOpenPoseDimensions($this->getOpenPoseDimensions());

                $action = new Action(
                    $machine,
                    $pressSheet,
                    $zone,
                    $this->layoutCalculator
                );
            }

            foreach ($action->getGridFittings() as $gridFitting) {
                $gridFitting->getCutSheet()->setContentType("Sheet");

                $node = new ActionTreeNode(
                    $action->getMachine(),
                    $action->getPressSheet(),
                    $action->getZone(),
                    $gridFitting,
                    []
                );

                $node->setPrevActions($this->calculate(
                    $abstractActions,
                    $pressSheet,
                    $gridFitting->getCutSheet(),
                    $prevNodes,
                    $inking
                ));

                $actionPaths[] = $node;
            }
        }

        return $actionPaths;
    }

    /**
     * Filter machines to only those with enough colors for the inking.
     *
     * @param array $machines Available machines
     * @param array $inking Inking specification
     * @return array Machines with sufficient color capacity
     */
    private function filterMachinesByColorCapability(array $machines, array $inking): array
    {
        $versoInking = $this->propertyAccessor->getValue($this->getInking(), "[verso]") ?: [];
        $maxColorsPerSide = max(count($inking["recto"] ?? []), count($versoInking));

        return array_filter($machines, function ($machine) use ($maxColorsPerSide) {
            return $machine->getNumberOfColors() >= $maxColorsPerSide;
        });
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
        $forwardPaths = [];
        foreach ($this->getRoot() as $rootNode) {
            $forwardPaths = array_merge($forwardPaths, $this->flatten($rootNode, []));
        }

        // Reverse paths to get backtrace order
        $backtracePaths = [];
        foreach ($forwardPaths as $forwardPath) {
            $backtracePaths[] = array_reverse($forwardPath);
        }

        return $backtracePaths;
    }

    /**
     * Recursively flatten a tree node into paths.
     *
     * Traverses from root to leaves, collecting all possible paths.
     * Each node is cloned to prevent modification of the original tree.
     *
     * @param ActionTreeNodeInterface $node Current node to process
     * @param ActionTreeNodeInterface[] $path Current path being built
     * @return ActionTreeNodeInterface[][] All paths from this node to leaves
     */
    protected function flatten(ActionTreeNodeInterface $node, array $path = []): array
    {
        $current = clone $node;
        $current->setPrevActions([]);
        $path[] = $current;

        if (empty($node->getPrevActions())) {
            return [$path];
        }

        $result = [];
        foreach ($node->getPrevActions() as $prevNode) {
            $subPaths = $this->flatten($prevNode, $path);
            foreach ($subPaths as $subPath) {
                $result[] = $subPath;
            }
        }

        return $result;
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
        return $this->extendPaths(
            $abstractActions,
            $pressSheets,
            $zone,
            $openPoseDimensions,
            $closedPoseDimensions,
            $numberOfCopies,
            $numberOfColors,
            $paperWeight,
            $inking
        );
    }

    /**
     * Build trees, flatten, and extend paths for all press sheets.
     */
    protected function extendPaths(
        array $abstractActions,
        array $pressSheets,
        InputSheetInterface $zone,
        DimensionsInterface $openPoseDimensions,
        DimensionsInterface $closedPoseDimensions,
        float $numberOfCopies,
        float $numberOfColors,
        float $paperWeight,
        array $inking
    ): array {
        $this->setOpenPoseDimensions($openPoseDimensions);
        $this->setClosedPoseDimensions($closedPoseDimensions);
        $this->setNumberOfCopies($numberOfCopies);
        $this->setNumberOfColors($numberOfColors);
        $this->setPaperWeight($paperWeight);
        $this->setInking($inking);

        $extendedFlatActionPaths = [];

        foreach ($pressSheets as $pressSheet) {
            $this->calculateTree($abstractActions, $pressSheet, $zone, [], $inking);
            $flatActionPaths = $this->flattenTree();

            foreach ($flatActionPaths as $flatActionPath) {
                $extendedFlatActionPaths[] = $this->extend($flatActionPath);
            }
        }

        return $extendedFlatActionPaths;
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
        if ($this->pipeline !== null) {
            return $this->extendWithPipeline($flatActionPath);
        }

        return $this->extendLegacy($flatActionPath);
    }

    /**
     * Extend using the pipeline architecture.
     */
    protected function extendWithPipeline(array $flatActionPath): array
    {
        $params = new ExtensionParams(
            numberOfCopies: $this->numberOfCopies,
            numberOfColors: $this->numberOfColors,
            paperWeight: $this->paperWeight,
            inking: $this->inking,
            openPoseDimensions: $this->openPoseDimensions,
            closedPoseDimensions: $this->closedPoseDimensions,
        );

        $context = new ActionPathContext(
            nodes: [],
            cutSheetCount: $this->numberOfCopies,
            params: $params,
            originalPath: $flatActionPath,
        );

        $result = $this->pipeline->process($context);

        return $result->nodes;
    }

    /**
     * Legacy extend implementation.
     *
     * @deprecated Will be removed in Phase 6. Use pipeline-based extend() instead.
     */
    public function extendLegacy(array $flatActionPath): array
    {
        $cutSheetCount = $this->getNumberOfCopies();
        $extendedActionPath = [];

        foreach ($flatActionPath as $index => $originalNode) {
            $node = clone $originalNode;
            $nextAction = $flatActionPath[$index + 1] ?? null;
            $machineType = $node->getMachine()->getType();

            // Handle printing press: insert CTP and set todo
            if ($machineType === MachineType::PrintingPress) {
                $extendedActionPath[] = $this->createCtpAction($node, $cutSheetCount);
                $node->setTodo([
                    'numberOfCopies' => $this->numberOfCopies,
                    'numberOfColors' => $this->numberOfColors,
                    'paperWeight' => $this->paperWeight,
                    'cutSheetCount' => $cutSheetCount,
                ]);
            }

            // Handle folder
            if ($machineType === MachineType::Folder) {
                $inputSheetLength = $this->openPoseDimensions->getHeight() / 1000;
                $node->setTodo([
                    'openPoseDimensions' => [
                        'width' => $this->openPoseDimensions->getWidth(),
                        'height' => $this->openPoseDimensions->getHeight(),
                    ],
                    'closedPoseDimensions' => [
                        'width' => $this->closedPoseDimensions->getWidth(),
                        'height' => $this->closedPoseDimensions->getHeight(),
                    ],
                    'inputSheetLength' => $inputSheetLength,
                    'cutSheetCount' => $cutSheetCount,
                    'numberOfCopies' => $this->numberOfCopies,
                ]);
            }

            // Handle stitching machine
            if ($machineType === MachineType::StitchingMachine) {
                $node->setTodo([
                    'numberOfCopies' => $this->numberOfCopies,
                    'cutSheetCount' => $cutSheetCount,
                ]);
            }

            // Handle cutout machine
            if ($machineType === MachineType::CutoutMachine) {
                $node->setTodo([
                    'numberOfCopies' => $this->numberOfCopies,
                    'cutSheetCount' => $cutSheetCount,
                    'openPoseDimensions' => [
                        'width' => $this->openPoseDimensions->getWidth(),
                        'height' => $this->openPoseDimensions->getHeight(),
                    ],
                    'closedPoseDimensions' => [
                        'width' => $this->closedPoseDimensions->getWidth(),
                        'height' => $this->closedPoseDimensions->getHeight(),
                    ],
                ]);
            }

            // Handle splitter
            if ($machineType === MachineType::Splitter) {
                $node->setTodo([
                    'numberOfCopies' => $this->numberOfCopies,
                    'cutSheetCount' => $cutSheetCount,
                ]);
            }

            // Handle assembler
            if ($machineType === MachineType::Assembler) {
                $node->setTodo([
                    'numberOfCopies' => $this->numberOfCopies,
                    'cutSheetCount' => $cutSheetCount,
                ]);
            }

            $extendedActionPath[] = $node;

            // Handle verso printing
            if ($machineType === MachineType::PrintingPress) {
                $versoAction = $this->createVersoActionIfNeeded($node, $cutSheetCount);
                if ($versoAction !== null) {
                    $extendedActionPath[] = $versoAction;
                }
            }

            // Handle cutting
            $cuttingInfo = $this->calculateCuttingInfo($node, $nextAction);
            if ($cuttingInfo['numberOfCuts'] > 0) {
                $extendedActionPath[] = $this->createCuttingAction($node, $cuttingInfo, $cutSheetCount);
            }

            if ($cuttingInfo['cutCuts'] > 0) {
                $cutSheetCount *= $node->getGridFitting()->getCols() * $node->getGridFitting()->getRows();
            }
        }

        return $extendedActionPath;
    }

    /**
     * Create a CTP action for a printing press node.
     */
    private function createCtpAction(ActionTreeNodeInterface $node, float $cutSheetCount): ActionPathNode
    {
        $ctpMachine = $this->equipmentFactory->fromId('ctp-machine');
        $ctpAction = new ActionPathNode(
            $ctpMachine,
            $node->getPressSheet(),
            $node->getZone(),
            clone $node->getGridFitting(),
            [
                'numberOfCopies' => $this->numberOfCopies,
                'numberOfColors' => $this->numberOfColors,
                'cutSheetCount' => $cutSheetCount,
                'inking' => $this->getInking(),
            ]
        );

        $ctpAction->getGridFitting()->setExplanation([
            'machine' => [
                'name' => $ctpMachine->getId(),
                'minSheet' => $ctpMachine->getMinSheetDimensions(),
                'maxSheet' => $ctpMachine->getMaxSheetDimensions(),
            ],
        ]);

        return $ctpAction;
    }

    /**
     * Create a verso printing action if verso inking is present.
     */
    private function createVersoActionIfNeeded(ActionTreeNodeInterface $node, float $cutSheetCount): ?ActionPathNode
    {
        $versoInking = $this->propertyAccessor->getValue($this->getInking(), '[verso]');
        if (!is_array($versoInking) || $versoInking === []) {
            return null;
        }

        $versoAction = new ActionPathNode(
            $node->getMachine(),
            $node->getPressSheet(),
            $node->getZone(),
            clone $node->getGridFitting(),
            [
                'numberOfCopies' => $this->numberOfCopies,
                'numberOfColors' => $this->numberOfColors,
                'cutSheetCount' => $cutSheetCount,
                'inking' => $this->getInking(),
            ]
        );

        $versoAction->setTodo([
            'numberOfCopies' => $this->numberOfCopies,
            'numberOfColors' => $this->numberOfColors,
            'paperWeight' => $this->paperWeight,
            'cutSheetCount' => $cutSheetCount,
            'dryTimeBetweenSequences' => 0,
        ]);

        return $versoAction;
    }

    /**
     * Calculate cutting information (trim cuts and cut cuts).
     */
    private function calculateCuttingInfo(ActionTreeNodeInterface $node, ?ActionTreeNodeInterface $nextAction): array
    {
        $trimCuts = 0;
        $cutCuts = 0;

        if ($nextAction !== null) {
            $currentMaxSheet = $node->getMachine()->getMaxSheetDimensions();
            $nextZone = $nextAction->getZone()->getDimensions();

            if ($currentMaxSheet->getWidth() !== $nextZone->getWidth()
                || $currentMaxSheet->getHeight() !== $nextZone->getHeight()
            ) {
                $trimLines = $node->getGridFitting()->getTrimLines();
                $trimCuts += ($trimLines['top']['y'] > 0) ? 2 : 0;
                $trimCuts += ($trimLines['left']['x'] > 0) ? 2 : 0;
            }

            $cutCuts = $node->getGridFitting()->getCols() - 1 + $node->getGridFitting()->getRows() - 1;
        }

        return [
            'trimCuts' => $trimCuts,
            'cutCuts' => $cutCuts,
            'numberOfCuts' => $trimCuts + $cutCuts,
        ];
    }

    /**
     * Create a cutting action.
     */
    private function createCuttingAction(
        ActionTreeNodeInterface $node,
        array $cuttingInfo,
        float $cutSheetCount
    ): ActionPathNode {
        return new ActionPathNode(
            $this->equipmentFactory->fromId('cutting-machine'),
            $node->getPressSheet(),
            $node->getZone(),
            clone $node->getGridFitting(),
            [
                'numberOfCuts' => $cuttingInfo['numberOfCuts'],
                'numberOfCopies' => $this->numberOfCopies,
                'numberOfColors' => $this->numberOfColors,
                'paperWeight' => $this->paperWeight,
                'cutSheetCount' => $cutSheetCount,
                'trimCuts' => $cuttingInfo['trimCuts'],
                'cuts' => $cuttingInfo['cutCuts'],
            ]
        );
    }
}