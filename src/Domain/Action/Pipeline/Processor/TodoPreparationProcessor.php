<?php

namespace App\Domain\Action\Pipeline\Processor;

use App\Domain\Action\ActionPathNode;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathProcessorInterface;

/**
 * Processor that calculates enrichment for each node.
 * Delegates to Machine::calculateEnrichment() for machine-specific logic.
 *
 * Previously named TodoPreparationProcessor, now uses the new enrichment system.
 */
class TodoPreparationProcessor implements ActionPathProcessorInterface
{
    public function process(ActionPathContext $context): ActionPathContext
    {
        $nodes = [];
        $cutSheetCount = $context->cutSheetCount;

        foreach ($context->originalPath as $originalNode) {
            /** @var ActionTreeNodeInterface $originalNode */
            $node = clone $originalNode;

            // Calculate enrichment using the new method
            $enrichment = $node->getMachine()->calculateEnrichment(
                $context->jobContext,
                $node->getGridFitting(),
                $node->getPressSheet(),
                $cutSheetCount,
            );

            // Create ActionPathNode with the enrichment and preserve printParams
            $actionPathNode = new ActionPathNode(
                $node->getMachine(),
                $node->getPressSheet(),
                $node->getZone(),
                $node->getGridFitting(),
                $enrichment,
                $node->getPrintParams()  // Preserve print-specific parameters
            );

            $nodes[] = $actionPathNode;
        }

        return new ActionPathContext(
            $nodes,
            $cutSheetCount,
            $context->jobContext,
            $context->originalPath
        );
    }

    public function getPriority(): int
    {
        return 10;
    }
}
