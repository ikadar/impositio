<?php

namespace App\Domain\Action\Pipeline\Processor;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathProcessorInterface;
use App\Domain\Equipment\Enrichment\CuttingEnrichment;

/**
 * Processor that tracks the cutSheetCount throughout the action path.
 * After cutting, the number of sheets multiplies by the grid size (cols * rows).
 *
 * With the new enrichment system, nodes already contain the correct cutSheetCount
 * from calculateEnrichment(). This processor now only updates the context's
 * cutSheetCount for tracking purposes.
 */
class CutSheetCountProcessor implements ActionPathProcessorInterface
{
    public function process(ActionPathContext $context): ActionPathContext
    {
        $cutSheetCount = $context->cutSheetCount;

        foreach ($context->nodes as $node) {
            /** @var ActionPathNodeInterface $node */

            // After cutting, update the sheet count for subsequent processing
            if ($node->getMachine()->getType()->value === 'cutting machine') {
                $enrichment = $node->getEnrichment();

                // Use typed getter if available, otherwise fall back to array
                if ($enrichment instanceof CuttingEnrichment) {
                    $cutCuts = $enrichment->getCuts();
                } else {
                    $enrichmentData = $enrichment->toArray();
                    $cutCuts = $enrichmentData['cuts'] ?? 0;
                }

                if ($cutCuts > 0) {
                    $cols = $node->getGridFitting()->getCols();
                    $rows = $node->getGridFitting()->getRows();
                    $cutSheetCount = $cutSheetCount * $cols * $rows;
                }
            }
        }

        // Return context with updated cutSheetCount (nodes unchanged)
        return new ActionPathContext(
            $context->nodes,
            $cutSheetCount,
            $context->jobContext,
            $context->originalPath
        );
    }

    public function getPriority(): int
    {
        return 50;
    }
}
