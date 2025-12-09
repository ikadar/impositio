<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\MachineType;
use App\Domain\Layout\Calculator;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Builds action trees from abstract actions.
 *
 * Responsible for:
 * - Building a tree of possible machine assignments (calculateTree/calculate)
 * - Filtering machines by capability (color count, etc.)
 * - Creating ActionTreeNodes for each machine/gridFitting combination
 */
class ActionTreeBuilder
{
    public function __construct(
        private Calculator $layoutCalculator,
        private PropertyAccessorInterface $propertyAccessor,
    ) {}

    /**
     * Build the action tree for a given set of abstract actions.
     *
     * @param AbstractActionInterface[] $abstractActions Actions to process
     * @param PressSheetInterface $pressSheet The press sheet to use
     * @param InputSheetInterface $zone The zone/input sheet
     * @param array $inking Inking specification ['recto' => [...], 'verso' => [...]]
     * @param TreeBuildContext $context Build context with dimensions and other state
     * @return ActionTreeNodeInterface[] Root nodes of the built tree
     */
    public function buildTree(
        array $abstractActions,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        array $inking,
        TreeBuildContext $context,
    ): array {
        return $this->build($abstractActions, $pressSheet, $zone, $inking, $context);
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
     * @param array $inking Inking specification
     * @param TreeBuildContext $context Build context
     * @return ActionTreeNodeInterface[] Tree nodes for this level
     */
    private function build(
        array $abstractActions,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        array $inking,
        TreeBuildContext $context,
    ): array {
        /** @var AbstractActionInterface|null $abstractAction */
        $abstractAction = array_shift($abstractActions);

        if ($abstractAction === null) {
            return [];
        }

        $availableMachines = $abstractAction->getAvailableMachines();

        // Filter printing presses by color capability
        if ($abstractAction->getMachineType() === MachineType::PrintingPress) {
            $availableMachines = $this->filterMachinesByColorCapability($availableMachines, $inking, $context);
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
                $zone->setDimensions($context->getOpenPoseDimensions());
                $machine->setOpenPoseDimensions($context->getOpenPoseDimensions());

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

                $node->setPrevActions($this->build(
                    $abstractActions,
                    $pressSheet,
                    $gridFitting->getCutSheet(),
                    $inking,
                    $context
                ));

                $actionPaths[] = $node;
            }
        }

        return $actionPaths;
    }

    /**
     * Filter machines to only those with enough colors for the inking.
     *
     * @param MachineInterface[] $machines Available machines
     * @param array $inking Inking specification
     * @param TreeBuildContext $context Build context with full inking
     * @return MachineInterface[] Machines with sufficient color capacity
     */
    private function filterMachinesByColorCapability(array $machines, array $inking, TreeBuildContext $context): array
    {
        $versoInking = $this->propertyAccessor->getValue($context->getInking(), "[verso]") ?: [];
        $maxColorsPerSide = max(count($inking["recto"] ?? []), count($versoInking));

        return array_filter($machines, function ($machine) use ($maxColorsPerSide) {
            return $machine->getNumberOfColors() >= $maxColorsPerSide;
        });
    }
}
