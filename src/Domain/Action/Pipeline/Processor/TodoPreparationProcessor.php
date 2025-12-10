<?php

namespace App\Domain\Action\Pipeline\Processor;

use App\Domain\Action\ActionPathNode;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathProcessorInterface;
use App\Domain\Equipment\TodoContext;

/**
 * Processor that prepares todo arrays for each node.
 * Delegates to Machine::prepareTodo() for machine-specific logic.
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

            // Create TodoContext for the machine
            $todoContext = new TodoContext(
                numberOfCopies: $context->params->numberOfCopies,
                numberOfColors: $context->params->numberOfColors,
                paperWeight: $context->params->paperWeight,
                inking: $context->params->inking,
                openPoseDimensions: $context->params->openPoseDimensions,
                closedPoseDimensions: $context->params->closedPoseDimensions,
                cutSheetCount: $cutSheetCount,
                gridFitting: $node->getGridFitting(),
                pressSheet: $node->getPressSheet(),
            );

            // Delegate to machine to prepare its todo
            $todo = $node->getMachine()->prepareTodo($todoContext);

            // Create ActionPathNode with the prepared todo
            $actionPathNode = new ActionPathNode(
                $node->getMachine(),
                $node->getPressSheet(),
                $node->getZone(),
                $node->getGridFitting(),
                $todo
            );

            $nodes[] = $actionPathNode;
        }

        return new ActionPathContext(
            $nodes,
            $cutSheetCount,
            $context->params,
            $context->originalPath
        );
    }

    public function getPriority(): int
    {
        return 10;
    }
}
