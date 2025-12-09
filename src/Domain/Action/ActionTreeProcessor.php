<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathPipeline;
use App\Domain\Action\Pipeline\ExtensionParams;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\MachineType;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Orchestrates action tree processing.
 *
 * Responsible for:
 * - Coordinating tree building, flattening, and path extension
 * - Managing the overall workflow
 * - Delegating to specialized classes for each step
 */
class ActionTreeProcessor
{
    public function __construct(
        private ActionTreeBuilder $builder,
        private ActionTreeFlattener $flattener,
        private ?ActionPathPipeline $pipeline,
        private EquipmentFactoryInterface $equipmentFactory,
        private PropertyAccessorInterface $propertyAccessor,
    ) {}

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
     * @param TreeBuildContext $context Build context with all parameters
     * @return ActionPathNodeInterface[][] Extended action paths
     */
    public function process(
        array $abstractActions,
        array $pressSheets,
        InputSheetInterface $zone,
        TreeBuildContext $context,
    ): array {
        $extendedFlatActionPaths = [];

        foreach ($pressSheets as $pressSheet) {
            $rootNodes = $this->builder->buildTree(
                $abstractActions,
                $pressSheet,
                $zone,
                $context->getInking(),
                $context
            );

            $flatActionPaths = $this->flattener->flattenTree($rootNodes);

            foreach ($flatActionPaths as $flatActionPath) {
                $extendedFlatActionPaths[] = $this->extend($flatActionPath, $context);
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
     * @param TreeBuildContext $context Build context
     * @return ActionPathNodeInterface[] Extended action path with inserted actions
     */
    public function extend(array $flatActionPath, TreeBuildContext $context): array
    {
        if ($this->pipeline !== null) {
            return $this->extendWithPipeline($flatActionPath, $context);
        }

        return $this->extendLegacy($flatActionPath, $context);
    }

    /**
     * Extend using the pipeline architecture.
     */
    private function extendWithPipeline(array $flatActionPath, TreeBuildContext $context): array
    {
        $params = new ExtensionParams(
            numberOfCopies: $context->getNumberOfCopies(),
            numberOfColors: $context->getNumberOfColors(),
            paperWeight: $context->getPaperWeight(),
            inking: $context->getInking(),
            openPoseDimensions: $context->getOpenPoseDimensions(),
            closedPoseDimensions: $context->getClosedPoseDimensions(),
        );

        $pipelineContext = new ActionPathContext(
            nodes: [],
            cutSheetCount: $context->getNumberOfCopies(),
            params: $params,
            originalPath: $flatActionPath,
        );

        $result = $this->pipeline->process($pipelineContext);

        return $result->nodes;
    }

    /**
     * Legacy extend implementation.
     *
     * @deprecated Will be removed in Phase 6. Use pipeline-based extend() instead.
     */
    public function extendLegacy(array $flatActionPath, TreeBuildContext $context): array
    {
        $cutSheetCount = $context->getNumberOfCopies();
        $extendedActionPath = [];

        foreach ($flatActionPath as $index => $originalNode) {
            $node = clone $originalNode;
            $nextAction = $flatActionPath[$index + 1] ?? null;
            $machineType = $node->getMachine()->getType();

            // Handle printing press: insert CTP and set todo
            if ($machineType === MachineType::PrintingPress) {
                $extendedActionPath[] = $this->createCtpAction($node, $cutSheetCount, $context);
                $node->setTodo([
                    'numberOfCopies' => $context->getNumberOfCopies(),
                    'numberOfColors' => $context->getNumberOfColors(),
                    'paperWeight' => $context->getPaperWeight(),
                    'cutSheetCount' => $cutSheetCount,
                ]);
            }

            // Handle folder
            if ($machineType === MachineType::Folder) {
                $inputSheetLength = $context->getOpenPoseDimensions()->getHeight() / 1000;
                $node->setTodo([
                    'openPoseDimensions' => [
                        'width' => $context->getOpenPoseDimensions()->getWidth(),
                        'height' => $context->getOpenPoseDimensions()->getHeight(),
                    ],
                    'closedPoseDimensions' => [
                        'width' => $context->getClosedPoseDimensions()->getWidth(),
                        'height' => $context->getClosedPoseDimensions()->getHeight(),
                    ],
                    'inputSheetLength' => $inputSheetLength,
                    'cutSheetCount' => $cutSheetCount,
                    'numberOfCopies' => $context->getNumberOfCopies(),
                ]);
            }

            // Handle stitching machine
            if ($machineType === MachineType::StitchingMachine) {
                $node->setTodo([
                    'numberOfCopies' => $context->getNumberOfCopies(),
                    'cutSheetCount' => $cutSheetCount,
                ]);
            }

            // Handle cutout machine
            if ($machineType === MachineType::CutoutMachine) {
                $node->setTodo([
                    'numberOfCopies' => $context->getNumberOfCopies(),
                    'cutSheetCount' => $cutSheetCount,
                    'openPoseDimensions' => [
                        'width' => $context->getOpenPoseDimensions()->getWidth(),
                        'height' => $context->getOpenPoseDimensions()->getHeight(),
                    ],
                    'closedPoseDimensions' => [
                        'width' => $context->getClosedPoseDimensions()->getWidth(),
                        'height' => $context->getClosedPoseDimensions()->getHeight(),
                    ],
                ]);
            }

            // Handle splitter
            if ($machineType === MachineType::Splitter) {
                $node->setTodo([
                    'numberOfCopies' => $context->getNumberOfCopies(),
                    'cutSheetCount' => $cutSheetCount,
                ]);
            }

            // Handle assembler
            if ($machineType === MachineType::Assembler) {
                $node->setTodo([
                    'numberOfCopies' => $context->getNumberOfCopies(),
                    'cutSheetCount' => $cutSheetCount,
                ]);
            }

            $extendedActionPath[] = $node;

            // Handle verso printing
            if ($machineType === MachineType::PrintingPress) {
                $versoAction = $this->createVersoActionIfNeeded($node, $cutSheetCount, $context);
                if ($versoAction !== null) {
                    $extendedActionPath[] = $versoAction;
                }
            }

            // Handle cutting
            $cuttingInfo = $this->calculateCuttingInfo($node, $nextAction);
            if ($cuttingInfo['numberOfCuts'] > 0) {
                $extendedActionPath[] = $this->createCuttingAction($node, $cuttingInfo, $cutSheetCount, $context);
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
    private function createCtpAction(ActionTreeNodeInterface $node, float $cutSheetCount, TreeBuildContext $context): ActionPathNode
    {
        $ctpMachine = $this->equipmentFactory->fromId('ctp-machine');
        $ctpAction = new ActionPathNode(
            $ctpMachine,
            $node->getPressSheet(),
            $node->getZone(),
            clone $node->getGridFitting(),
            [
                'numberOfCopies' => $context->getNumberOfCopies(),
                'numberOfColors' => $context->getNumberOfColors(),
                'cutSheetCount' => $cutSheetCount,
                'inking' => $context->getInking(),
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
    private function createVersoActionIfNeeded(ActionTreeNodeInterface $node, float $cutSheetCount, TreeBuildContext $context): ?ActionPathNode
    {
        $versoInking = $this->propertyAccessor->getValue($context->getInking(), '[verso]');
        if (!is_array($versoInking) || $versoInking === []) {
            return null;
        }

        $versoAction = new ActionPathNode(
            $node->getMachine(),
            $node->getPressSheet(),
            $node->getZone(),
            clone $node->getGridFitting(),
            [
                'numberOfCopies' => $context->getNumberOfCopies(),
                'numberOfColors' => $context->getNumberOfColors(),
                'cutSheetCount' => $cutSheetCount,
                'inking' => $context->getInking(),
            ]
        );

        $versoAction->setTodo([
            'numberOfCopies' => $context->getNumberOfCopies(),
            'numberOfColors' => $context->getNumberOfColors(),
            'paperWeight' => $context->getPaperWeight(),
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
        float $cutSheetCount,
        TreeBuildContext $context
    ): ActionPathNode {
        return new ActionPathNode(
            $this->equipmentFactory->fromId('cutting-machine'),
            $node->getPressSheet(),
            $node->getZone(),
            clone $node->getGridFitting(),
            [
                'numberOfCuts' => $cuttingInfo['numberOfCuts'],
                'numberOfCopies' => $context->getNumberOfCopies(),
                'numberOfColors' => $context->getNumberOfColors(),
                'paperWeight' => $context->getPaperWeight(),
                'cutSheetCount' => $cutSheetCount,
                'trimCuts' => $cuttingInfo['trimCuts'],
                'cuts' => $cuttingInfo['cutCuts'],
            ]
        );
    }
}
