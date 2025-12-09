<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathPipeline;
use App\Domain\Action\Pipeline\ExtensionParams;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

/**
 * Orchestrates action tree processing.
 *
 * Responsible for:
 * - Coordinating tree building, flattening, and path extension
 * - Managing the overall workflow
 * - Delegating to specialized classes for each step
 *
 * Phase 6: Legacy code removed. Pipeline is now required for path extension.
 */
class ActionTreeProcessor
{
    public function __construct(
        private ActionTreeBuilder $builder,
        private ActionTreeFlattener $flattener,
        private ActionPathPipeline $pipeline,
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
     * Uses the pipeline architecture for path extension.
     *
     * @param ActionTreeNodeInterface[] $flatActionPath The flattened action path
     * @param TreeBuildContext $context Build context
     * @return ActionPathNodeInterface[] Extended action path with inserted actions
     */
    public function extend(array $flatActionPath, TreeBuildContext $context): array
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
}
