<?php

namespace App\Domain\Action\Pipeline\Processor;

use App\Domain\Action\ActionPathNode;
use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathProcessorInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\TodoContext;

/**
 * Processor that inserts CTP action before printing press actions.
 * CTP (Computer to Plate) is required for offset printing.
 */
class CtpInsertionProcessor implements ActionPathProcessorInterface
{
    public function __construct(
        private EquipmentFactoryInterface $equipmentFactory,
    ) {}

    public function process(ActionPathContext $context): ActionPathContext
    {
        $newNodes = [];

        foreach ($context->nodes as $node) {
            /** @var ActionPathNodeInterface $node */

            // Check if this is a printing press action
            if ($node->getMachine()->getType()->value === 'printing press') {
                // Insert CTP action before printing press
                $ctpAction = $this->createCtpAction($node, $context);
                $newNodes[] = $ctpAction;
            }

            $newNodes[] = $node;
        }

        return new ActionPathContext(
            $newNodes,
            $context->cutSheetCount,
            $context->params,
            $context->originalPath
        );
    }

    private function createCtpAction(
        ActionPathNodeInterface $printNode,
        ActionPathContext $context
    ): ActionPathNode {
        $ctpMachine = $this->equipmentFactory->fromId('ctp-machine');

        // Create TodoContext for CTP machine
        $todoContext = new TodoContext(
            numberOfCopies: $context->params->numberOfCopies,
            numberOfColors: $context->params->numberOfColors,
            paperWeight: $context->params->paperWeight,
            inking: $context->params->inking,
            openPoseDimensions: $context->params->openPoseDimensions,
            closedPoseDimensions: $context->params->closedPoseDimensions,
            cutSheetCount: $context->cutSheetCount,
        );

        $todo = $ctpMachine->prepareTodo($todoContext);

        // Add explanation for the CTP action
        $gridFitting = clone $printNode->getGridFitting();
        $explanation = [
            'machine' => [
                'name' => $ctpMachine->getId(),
                'minSheet' => $ctpMachine->getMinSheetDimensions(),
                'maxSheet' => $ctpMachine->getMaxSheetDimensions(),
            ]
        ];
        $gridFitting->setExplanation($explanation);

        return new ActionPathNode(
            $ctpMachine,
            $printNode->getPressSheet(),
            $printNode->getZone(),
            $gridFitting,
            $todo
        );
    }

    public function getPriority(): int
    {
        return 20;
    }
}
