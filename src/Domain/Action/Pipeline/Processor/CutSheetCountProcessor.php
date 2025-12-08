<?php

namespace App\Domain\Action\Pipeline\Processor;

use App\Domain\Action\ActionPathNode;
use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathProcessorInterface;

/**
 * Processor that updates the cutSheetCount throughout the action path.
 * After cutting, the number of sheets multiplies by the grid size (cols * rows).
 *
 * This processor updates the todo arrays with the correct cutSheetCount values
 * as the sheet count changes throughout the process.
 */
class CutSheetCountProcessor implements ActionPathProcessorInterface
{
    public function process(ActionPathContext $context): ActionPathContext
    {
        $newNodes = [];
        $cutSheetCount = $context->cutSheetCount;

        foreach ($context->nodes as $node) {
            /** @var ActionPathNodeInterface $node */

            // Update the node's todo with current cutSheetCount
            $todo = $node->getTodo();
            $todo['cutSheetCount'] = $cutSheetCount;

            // Create new node with updated todo
            $newNode = new ActionPathNode(
                $node->getMachine(),
                $node->getPressSheet(),
                $node->getZone(),
                $node->getGridFitting(),
                $todo
            );

            $newNodes[] = $newNode;

            // After cutting, update the sheet count
            if ($node->getMachine()->getType()->value === 'cutting machine') {
                $cutCuts = $todo['cuts'] ?? 0;
                if ($cutCuts > 0) {
                    $cols = $node->getGridFitting()->getCols();
                    $rows = $node->getGridFitting()->getRows();
                    $cutSheetCount = $cutSheetCount * $cols * $rows;
                }
            }
        }

        return new ActionPathContext(
            $newNodes,
            $cutSheetCount,
            $context->params,
            $context->originalPath
        );
    }

    public function getPriority(): int
    {
        return 50;
    }
}
