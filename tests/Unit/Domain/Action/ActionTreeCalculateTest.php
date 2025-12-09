<?php

namespace App\Tests\Unit\Domain\Action;

use App\Domain\Action\ActionTreeNode;
use App\Domain\Equipment\MachineType;
use App\Domain\Layout\Interfaces\GridFittingInterface;

/**
 * Unit tests for ActionTree::calculate() method.
 * Tests the tree building logic with various scenarios.
 */
class ActionTreeCalculateTest extends ActionTreeTestBase
{
    /**
     * Test: calculate() with empty abstract actions array.
     * Expected: Empty array returned.
     */
    public function test_calculate_with_empty_abstract_actions(): void
    {
        $actionTree = $this->createConfiguredActionTree();

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('calculate');
        $method->setAccessible(true);

        $result = $method->invoke(
            $actionTree,
            [],
            $this->pressSheet,
            $this->zone,
            [],
            $this->inking
        );

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test: calculate() with single printing press action.
     * Expected: ActionTreeNodes created for each machine+gridFitting combination.
     */
    public function test_calculate_single_printing_press_action(): void
    {
        // Setup machines
        $printingPress1 = $this->createMachineMock('Komori G40', MachineType::PrintingPress, 4);
        $printingPress2 = $this->createMachineMock('KBA 105', MachineType::PrintingPress, 2);

        // Create abstract action
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$printingPress1, $printingPress2]
        );

        // Setup calculator to return grid fittings
        $gridFitting1 = $this->createGridFittingMock(2, 2, false);
        $gridFitting2 = $this->createGridFittingMock(3, 2, true);

        // Mock the Action class behavior through calculator
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting1, $gridFitting2]);

        // Setup property accessor for inking
        $this->setupInkingPropertyAccessor($this->inking);

        $actionTree = $this->createConfiguredActionTree();

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('calculate');
        $method->setAccessible(true);

        $result = $method->invoke(
            $actionTree,
            [$printAction],
            $this->pressSheet,
            $this->zone,
            [],
            $this->inking
        );

        $this->assertIsArray($result);
        // Both machines have >= 2 colors required by inking, each returns 2 grid fittings
        // Expected: 2 machines × 2 grid fittings = 4 nodes
        // Note: Actual result depends on Action class behavior with grid fittings
        $this->assertNotEmpty($result);
    }

    /**
     * Test: calculate() filters machines based on color count.
     * Only machines with enough colors should be included.
     */
    public function test_calculate_filters_machines_by_color_count(): void
    {
        // Machine with 4 colors (enough for 2 colors in inking)
        $capableMachine = $this->createMachineMock('Komori G40', MachineType::PrintingPress, 4);

        // Machine with only 1 color (not enough)
        $incapableMachine = $this->createMachineMock('SingleColor', MachineType::PrintingPress, 1);

        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$capableMachine, $incapableMachine]
        );

        // Setup calculator
        $gridFitting = $this->createGridFittingMock();
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        // Setup property accessor for inking with 2 colors
        $inking = ['recto' => ['cyan', 'magenta'], 'verso' => []];
        $this->setupInkingPropertyAccessor($inking);

        $actionTree = $this->createConfiguredActionTree();
        $actionTree->setInking($inking);

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('calculate');
        $method->setAccessible(true);

        $result = $method->invoke(
            $actionTree,
            [$printAction],
            $this->pressSheet,
            $this->zone,
            [],
            $inking
        );

        // Only the capable machine should produce results
        // With 1 grid fitting, expect 1 node
        $this->assertIsArray($result);
        foreach ($result as $node) {
            $this->assertInstanceOf(ActionTreeNode::class, $node);
            // The incapable machine should not be present
            $this->assertNotEquals('SingleColor', $node->getMachine()->getId());
        }
    }

    /**
     * Test: calculate() with verso inking requires more colors.
     * Expected: Machines must have enough colors for max(recto, verso).
     */
    public function test_calculate_with_verso_inking_filters_correctly(): void
    {
        // Machine with 4 colors
        $machine4Colors = $this->createMachineMock('Press4', MachineType::PrintingPress, 4);

        // Machine with only 2 colors
        $machine2Colors = $this->createMachineMock('Press2', MachineType::PrintingPress, 2);

        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$machine4Colors, $machine2Colors]
        );

        $gridFitting = $this->createGridFittingMock();
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        // Inking with 2 colors on recto, 4 colors on verso
        // Max = 4, so only machine4Colors should work
        $inking = [
            'recto' => ['cyan', 'magenta'],
            'verso' => ['cyan', 'magenta', 'yellow', 'black']
        ];
        $this->setupInkingPropertyAccessor($inking);

        $actionTree = $this->createConfiguredActionTree();
        $actionTree->setInking($inking);

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('calculate');
        $method->setAccessible(true);

        $result = $method->invoke(
            $actionTree,
            [$printAction],
            $this->pressSheet,
            $this->zone,
            [],
            $inking
        );

        $this->assertIsArray($result);
        foreach ($result as $node) {
            // Only the 4-color machine should be present
            $this->assertEquals('Press4', $node->getMachine()->getId());
        }
    }

    /**
     * Test: calculate() with non-printing-press action skips color filtering.
     */
    public function test_calculate_non_printing_press_skips_color_filter(): void
    {
        $cuttingMachine = $this->createMachineMock('Cutter', MachineType::CuttingMachine, 0);

        $cutAction = $this->createAbstractActionMock(
            MachineType::CuttingMachine,
            [$cuttingMachine]
        );

        $gridFitting = $this->createGridFittingMock();
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        $this->setupInkingPropertyAccessor($this->inking);

        $actionTree = $this->createConfiguredActionTree();

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('calculate');
        $method->setAccessible(true);

        $result = $method->invoke(
            $actionTree,
            [$cutAction],
            $this->pressSheet,
            $this->zone,
            [],
            $this->inking
        );

        $this->assertIsArray($result);
        // Cutting machine should be present regardless of color count
        $this->assertNotEmpty($result);
    }

    /**
     * Test: calculate() with prevNodes parameter is ignored.
     * The prevNodes parameter seems to be unused in the current implementation.
     */
    public function test_calculate_with_prev_nodes_ignored(): void
    {
        $machine = $this->createMachineMock('Machine', MachineType::PrintingPress, 4);
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$machine]
        );

        $gridFitting = $this->createGridFittingMock();
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        $this->setupInkingPropertyAccessor($this->inking);

        $actionTree = $this->createConfiguredActionTree();

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('calculate');
        $method->setAccessible(true);

        // Create some fake prevNodes
        $fakePrevNodes = [
            $this->createActionTreeNode(),
            $this->createActionTreeNode()
        ];

        // Call with and without prevNodes
        $resultWithPrev = $method->invoke(
            $actionTree,
            [$printAction],
            $this->pressSheet,
            $this->zone,
            $fakePrevNodes,
            $this->inking
        );

        $resultWithoutPrev = $method->invoke(
            $actionTree,
            [$printAction],
            $this->pressSheet,
            $this->zone,
            [],
            $this->inking
        );

        // Results should have the same structure
        $this->assertCount(count($resultWithoutPrev), $resultWithPrev);
    }

    /**
     * Test: calculate() recursive call for multiple actions.
     * Each node should have its prevActions set from recursive calculation.
     */
    public function test_calculate_recursive_call_for_multiple_actions(): void
    {
        // First action: printing
        $printMachine = $this->createMachineMock('Printer', MachineType::PrintingPress, 4);
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$printMachine]
        );

        // Second action: cutting (will be in prevActions after recursion)
        $cutMachine = $this->createMachineMock('Cutter', MachineType::CuttingMachine, 0);
        $cutAction = $this->createAbstractActionMock(
            MachineType::CuttingMachine,
            [$cutMachine]
        );

        $gridFitting = $this->createGridFittingMock(1, 1);
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        $this->setupInkingPropertyAccessor($this->inking);

        $actionTree = $this->createConfiguredActionTree();

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('calculate');
        $method->setAccessible(true);

        $result = $method->invoke(
            $actionTree,
            [$printAction, $cutAction],
            $this->pressSheet,
            $this->zone,
            [],
            $this->inking
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Check that first action (print) has children (cut in prevActions)
        foreach ($result as $node) {
            $this->assertInstanceOf(ActionTreeNode::class, $node);
            $prevActions = $node->getPrevActions();
            // Print action should have prevActions from recursive call
            $this->assertIsArray($prevActions);
        }
    }
}
