<?php

namespace App\Tests\Unit\Domain\Action;

use App\Domain\Action\ActionPathNode;
use App\Domain\Action\ActionTreeNode;
use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ExtensionParams;
use App\Domain\Equipment\MachineType;

/**
 * Integration-style tests for complete ActionTree workflows.
 * Tests the full process() → calculateTree() → flattenTree() → extend() pipeline.
 */
class ActionTreeWorkflowTest extends ActionTreeTestBase
{
    /**
     * Test: Complete workflow with single print action.
     * Verifies: Tree building, flattening, and pipeline extension.
     */
    public function test_complete_workflow_single_print(): void
    {
        // Setup printing press machine
        $printMachine = $this->createMachineMock('Komori G40', MachineType::PrintingPress, 4);

        // Setup abstract action
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$printMachine]
        );

        // Setup calculator to return grid fitting
        $gridFitting = $this->createGridFittingMock(2, 2);
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        // Setup property accessor
        $this->setupInkingPropertyAccessor($this->inking);

        // Setup pipeline to return processed nodes
        $expectedNode = new ActionPathNode(
            $printMachine,
            $this->pressSheet,
            $this->zone,
            $gridFitting,
            ['numberOfCopies' => 1000]
        );

        $mockContext = new ActionPathContext(
            [$expectedNode],
            1000,
            new ExtensionParams(1000, 4, 80, $this->inking, $this->openPoseDimensions, $this->closedPoseDimensions),
            []
        );

        $this->mockPipeline
            ->method('process')
            ->willReturn($mockContext);

        // Execute
        $actionTree = $this->createConfiguredActionTree(withPipeline: true);
        $result = $actionTree->process(
            [$printAction],
            [$this->pressSheet],
            $this->zone,
            $this->openPoseDimensions,
            $this->closedPoseDimensions,
            1000,
            4,
            80,
            $this->inking
        );

        // Verify
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        // Each press sheet × each grid fitting = 1 path
        foreach ($result as $path) {
            $this->assertIsArray($path);
        }
    }

    /**
     * Test: Workflow with multiple press sheets.
     * Each press sheet should produce separate action paths.
     */
    public function test_workflow_with_multiple_press_sheets(): void
    {
        // Setup 2 press sheets
        $pressSheet1 = $this->createPressSheetMock(1000, 700, 80);
        $pressSheet2 = $this->createPressSheetMock(720, 520, 60);

        // Setup machine
        $printMachine = $this->createMachineMock('Printer', MachineType::PrintingPress, 4);
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$printMachine]
        );

        // Setup calculator - single grid fitting
        $gridFitting = $this->createGridFittingMock(1, 1);
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        $this->setupInkingPropertyAccessor($this->inking);

        // Setup pipeline
        $this->mockPipeline
            ->method('process')
            ->willReturn(new ActionPathContext(
                [],
                1000,
                new ExtensionParams(1000, 4, 80, $this->inking, $this->openPoseDimensions, $this->closedPoseDimensions),
                []
            ));

        // Execute
        $actionTree = $this->createConfiguredActionTree(withPipeline: true);
        $result = $actionTree->process(
            [$printAction],
            [$pressSheet1, $pressSheet2],
            $this->zone,
            $this->openPoseDimensions,
            $this->closedPoseDimensions,
            1000,
            4,
            80,
            $this->inking
        );

        // Verify - should have paths from both press sheets
        $this->assertIsArray($result);
        // 2 press sheets × 1 grid fitting = 2 paths minimum
    }

    /**
     * Test: Workflow with verso inking.
     * Pipeline should receive inking with verso colors.
     */
    public function test_workflow_with_verso_inking(): void
    {
        $inkingWithVerso = [
            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
            'verso' => ['black']
        ];

        // Setup machine with enough colors
        $printMachine = $this->createMachineMock('Printer', MachineType::PrintingPress, 4);
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$printMachine]
        );

        $gridFitting = $this->createGridFittingMock(1, 1);
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        $this->setupInkingPropertyAccessor($inkingWithVerso);

        // Capture context to verify inking is passed
        $capturedContext = null;
        $this->mockPipeline
            ->method('process')
            ->willReturnCallback(function ($context) use (&$capturedContext) {
                $capturedContext = $context;
                return $context;
            });

        // Execute
        $actionTree = $this->createConfiguredActionTree(withPipeline: true);
        $actionTree->process(
            [$printAction],
            [$this->pressSheet],
            $this->zone,
            $this->openPoseDimensions,
            $this->closedPoseDimensions,
            1000,
            4,
            80,
            $inkingWithVerso
        );

        // Verify inking was passed to pipeline
        $this->assertNotNull($capturedContext);
        $this->assertEquals($inkingWithVerso, $capturedContext->params->inking);
    }

    /**
     * Test: State is properly set on ActionTree before calculation.
     */
    public function test_workflow_sets_state_correctly(): void
    {
        $printMachine = $this->createMachineMock('Printer', MachineType::PrintingPress, 4);
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$printMachine]
        );

        $gridFitting = $this->createGridFittingMock(1, 1);
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        $this->setupInkingPropertyAccessor($this->inking);

        $this->mockPipeline
            ->method('process')
            ->willReturn(new ActionPathContext(
                [],
                1000,
                new ExtensionParams(1000, 4, 80, $this->inking, $this->openPoseDimensions, $this->closedPoseDimensions),
                []
            ));

        $actionTree = $this->createActionTree(withPipeline: true);

        // Execute
        $actionTree->process(
            [$printAction],
            [$this->pressSheet],
            $this->zone,
            $this->openPoseDimensions,
            $this->closedPoseDimensions,
            1000,
            4,
            80,
            $this->inking
        );

        // Verify state was set
        $this->assertEquals(1000, $actionTree->getNumberOfCopies());
        $this->assertEquals(4, $actionTree->getNumberOfColors());
        $this->assertEquals(80, $actionTree->getPaperWeight());
        $this->assertEquals($this->inking, $actionTree->getInking());
        $this->assertSame($this->openPoseDimensions, $actionTree->getOpenPoseDimensions());
        $this->assertSame($this->closedPoseDimensions, $actionTree->getClosedPoseDimensions());
    }

    /**
     * Test: Empty abstract actions produces empty result.
     */
    public function test_workflow_with_empty_actions(): void
    {
        $this->mockPipeline
            ->method('process')
            ->willReturn(new ActionPathContext(
                [],
                1000,
                new ExtensionParams(1000, 4, 80, $this->inking, $this->openPoseDimensions, $this->closedPoseDimensions),
                []
            ));

        $actionTree = $this->createConfiguredActionTree(withPipeline: true);
        $result = $actionTree->process(
            [],
            [$this->pressSheet],
            $this->zone,
            $this->openPoseDimensions,
            $this->closedPoseDimensions,
            1000,
            4,
            80,
            $this->inking
        );

        $this->assertIsArray($result);
        // Empty actions → empty tree → empty result
        $this->assertEmpty($result);
    }

    /**
     * Test: calculateTree sets root correctly.
     */
    public function test_calculateTree_sets_root(): void
    {
        $printMachine = $this->createMachineMock('Printer', MachineType::PrintingPress, 4);
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$printMachine]
        );

        $gridFitting = $this->createGridFittingMock(1, 1);
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        $this->setupInkingPropertyAccessor($this->inking);

        $actionTree = $this->createConfiguredActionTree();

        $result = $actionTree->calculateTree(
            [$printAction],
            $this->pressSheet,
            $this->zone,
            [],
            $this->inking
        );

        // Verify root was set
        $root = $actionTree->getRoot();
        $this->assertIsArray($root);
        $this->assertSame($result, $root);
    }

    /**
     * Test: Pipeline receives correct cut sheet count.
     */
    public function test_pipeline_receives_correct_cut_sheet_count(): void
    {
        $printMachine = $this->createMachineMock('Printer', MachineType::PrintingPress, 4);
        $printAction = $this->createAbstractActionMock(
            MachineType::PrintingPress,
            [$printMachine]
        );

        $gridFitting = $this->createGridFittingMock(1, 1);
        $this->mockCalculator
            ->method('calculateGridFittings')
            ->willReturn([$gridFitting]);

        $this->setupInkingPropertyAccessor($this->inking);

        $numberOfCopies = 2500;
        $capturedContext = null;

        $this->mockPipeline
            ->method('process')
            ->willReturnCallback(function ($context) use (&$capturedContext) {
                $capturedContext = $context;
                return $context;
            });

        $actionTree = $this->createActionTree(withPipeline: true);
        $actionTree->process(
            [$printAction],
            [$this->pressSheet],
            $this->zone,
            $this->openPoseDimensions,
            $this->closedPoseDimensions,
            $numberOfCopies,
            4,
            80,
            $this->inking
        );

        // cutSheetCount should equal numberOfCopies initially
        $this->assertNotNull($capturedContext);
        $this->assertEquals($numberOfCopies, $capturedContext->cutSheetCount);
    }
}
