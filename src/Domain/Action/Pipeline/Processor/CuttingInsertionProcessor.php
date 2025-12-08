<?php

namespace App\Domain\Action\Pipeline\Processor;

use App\Domain\Action\ActionPathNode;
use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ActionPathProcessorInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;

/**
 * Processor that inserts cutting actions when layout changes require it.
 * Cutting is needed for:
 * - Trim cuts: when the press sheet size doesn't match the next action's zone size
 * - Cut cuts: when grid has multiple columns/rows that need to be separated
 */
class CuttingInsertionProcessor implements ActionPathProcessorInterface
{
    public function __construct(
        private EquipmentFactoryInterface $equipmentFactory,
    ) {}

    public function process(ActionPathContext $context): ActionPathContext
    {
        $newNodes = [];
        $nodeCount = count($context->nodes);

        for ($i = 0; $i < $nodeCount; $i++) {
            /** @var ActionPathNodeInterface $currentNode */
            $currentNode = $context->nodes[$i];
            $newNodes[] = $currentNode;

            // Get next node if exists (in backtrace order, "next" is actually "previous" in production)
            $nextNode = ($i + 1 < $nodeCount) ? $context->nodes[$i + 1] : null;

            // Skip cutting insertion for CTP machine - CTP doesn't process physical sheets
            // Neither before nor after CTP should have cutting based on CTP dimensions
            if ($currentNode->getMachine()->getType()->value === 'ctp machine') {
                continue;
            }
            if ($nextNode !== null && $nextNode->getMachine()->getType()->value === 'ctp machine') {
                continue;
            }

            // Calculate cutting requirements
            $cuttingInfo = $this->calculateCuttingInfo($currentNode, $nextNode);

            if ($cuttingInfo['numberOfCuts'] > 0) {
                $cuttingAction = $this->createCuttingAction($currentNode, $cuttingInfo, $context);
                $newNodes[] = $cuttingAction;
            }
        }

        return new ActionPathContext(
            $newNodes,
            $context->cutSheetCount,
            $context->params,
            $context->originalPath
        );
    }

    /**
     * Calculate the number of trim and cut cuts needed.
     */
    private function calculateCuttingInfo(
        ActionPathNodeInterface $currentNode,
        ?ActionPathNodeInterface $nextNode
    ): array {
        $numberOfTrimCuts = 0;
        $numberOfCutCuts = 0;

        if ($nextNode !== null) {
            // Check if sheet dimensions change between current and next action
            $currentMaxSheet = $currentNode->getMachine()->getMaxSheetDimensions();
            $nextZone = $nextNode->getZone()->getDimensions();

            if (
                $currentMaxSheet->getWidth() !== $nextZone->getWidth()
                ||
                $currentMaxSheet->getHeight() !== $nextZone->getHeight()
            ) {
                $trimLines = $currentNode->getGridFitting()->getTrimLines();
                $numberOfTrimCuts += (($trimLines['top']['y'] > 0) ? 2 : 0);
                $numberOfTrimCuts += (($trimLines['left']['x'] > 0) ? 2 : 0);
            }

            // Grid cuts (separating poses)
            $numberOfCutCuts = $currentNode->getGridFitting()->getCols() - 1
                             + $currentNode->getGridFitting()->getRows() - 1;
        }

        return [
            'trimCuts' => $numberOfTrimCuts,
            'cutCuts' => $numberOfCutCuts,
            'numberOfCuts' => $numberOfTrimCuts + $numberOfCutCuts,
        ];
    }

    private function createCuttingAction(
        ActionPathNodeInterface $previousNode,
        array $cuttingInfo,
        ActionPathContext $context
    ): ActionPathNode {
        $cuttingMachine = $this->equipmentFactory->fromId('cutting-machine');

        $todo = [
            'numberOfCuts' => $cuttingInfo['numberOfCuts'],
            'numberOfCopies' => $context->params->numberOfCopies,
            'numberOfColors' => $context->params->numberOfColors,
            'paperWeight' => $context->params->paperWeight,
            'cutSheetCount' => $context->cutSheetCount,
            'trimCuts' => $cuttingInfo['trimCuts'],
            'cuts' => $cuttingInfo['cutCuts'],
        ];

        return new ActionPathNode(
            $cuttingMachine,
            $previousNode->getPressSheet(),
            $previousNode->getZone(),
            clone $previousNode->getGridFitting(),
            $todo
        );
    }

    public function getPriority(): int
    {
        return 40;
    }
}
