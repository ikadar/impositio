<?php

namespace App\Tests\Unit\Domain\Equipment;

use App\Domain\Equipment\CTPMachine;
use App\Domain\Equipment\MachineType;
use App\Domain\Equipment\OffsetPrintingPress;
use App\Domain\Equipment\TodoContext;
use App\Domain\Geometry\Dimensions;
use App\Domain\Layout\GridFitting;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\PressSheet;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Machine::prepareTodo() methods.
 *
 * These tests document and protect the exact cost calculation logic
 * for each machine type. They are critical for the todo -> ActionEnrichment
 * refactoring.
 */
class MachinePrepareTodoTest extends TestCase
{
    // ==================== OFFSET PRINTING PRESS ====================

    /**
     * Test: OffsetPrintingPress prepareTodo returns correct structure.
     */
    public function test_offset_press_prepareTodo_returns_correct_structure(): void
    {
        $press = $this->createOffsetPrintingPress();
        $context = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            gridFitting: $this->createGridFitting(cols: 5, rows: 2),
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo = $press->prepareTodo($context);

        // Structure assertions
        $this->assertArrayHasKey('numberOfCopies', $todo);
        $this->assertArrayHasKey('numberOfColors', $todo);
        $this->assertArrayHasKey('paperWeight', $todo);
        $this->assertArrayHasKey('cutSheetCount', $todo);
        $this->assertArrayHasKey('cost', $todo);

        // Cost structure
        $this->assertIsArray($todo['cost']);
        $this->assertArrayHasKey('cost', $todo['cost']);
        $this->assertArrayHasKey('paperCost', $todo['cost']);
    }

    /**
     * Test: OffsetPrintingPress calculates cutSheetCount correctly.
     */
    public function test_offset_press_calculates_cut_sheet_count(): void
    {
        $press = $this->createOffsetPrintingPress();

        // 1000 copies, 10 poses per sheet (5x2) = 100 sheets
        $context = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            gridFitting: $this->createGridFitting(cols: 5, rows: 2),
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo = $press->prepareTodo($context);

        $this->assertEquals(100, $todo['cutSheetCount']);
    }

    /**
     * Test: OffsetPrintingPress calculates cutSheetCount with ceiling.
     */
    public function test_offset_press_cut_sheet_count_uses_ceiling(): void
    {
        $press = $this->createOffsetPrintingPress();

        // 1001 copies, 10 poses per sheet = 101 sheets (ceiling)
        $context = $this->createTodoContext(
            numberOfCopies: 1001,
            numberOfColors: 4,
            gridFitting: $this->createGridFitting(cols: 5, rows: 2),
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo = $press->prepareTodo($context);

        $this->assertEquals(101, $todo['cutSheetCount']);
    }

    /**
     * Test: OffsetPrintingPress calculates paper cost correctly.
     */
    public function test_offset_press_calculates_paper_cost(): void
    {
        $press = $this->createOffsetPrintingPress();

        // 1000 copies, 10 poses per sheet, 0.10 EUR per sheet
        // Paper cost per product = 0.10 / 10 = 0.01
        // Total paper cost = 1000 * 0.01 = 10.00
        $context = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            gridFitting: $this->createGridFitting(cols: 5, rows: 2),
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo = $press->prepareTodo($context);

        $this->assertEqualsWithDelta(10.0, $todo['cost']['paperCost'], 0.01);
    }

    /**
     * Test: OffsetPrintingPress cost increases with number of colors.
     */
    public function test_offset_press_cost_increases_with_colors(): void
    {
        $press = $this->createOffsetPrintingPress();

        $context1Color = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 1,
            gridFitting: $this->createGridFitting(cols: 5, rows: 2),
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $context4Colors = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            gridFitting: $this->createGridFitting(cols: 5, rows: 2),
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo1Color = $press->prepareTodo($context1Color);
        $todo4Colors = $press->prepareTodo($context4Colors);

        $this->assertGreaterThan(
            $todo1Color['cost']['cost'],
            $todo4Colors['cost']['cost'],
            '4 colors should cost more than 1 color'
        );
    }

    // ==================== CTP MACHINE ====================

    /**
     * Test: CTPMachine prepareTodo returns correct structure.
     */
    public function test_ctp_machine_prepareTodo_returns_correct_structure(): void
    {
        $ctp = $this->createCTPMachine();
        $context = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo = $ctp->prepareTodo($context);

        // Structure assertions
        $this->assertArrayHasKey('numberOfCopies', $todo);
        $this->assertArrayHasKey('numberOfColors', $todo);
        $this->assertArrayHasKey('cutSheetCount', $todo);
        $this->assertArrayHasKey('inking', $todo);
        $this->assertArrayHasKey('cost', $todo);

        // Cost structure
        $this->assertIsArray($todo['cost']);
        $this->assertArrayHasKey('cost', $todo['cost']);
        $this->assertArrayHasKey('aluSheetsCost', $todo['cost']);
    }

    /**
     * Test: CTPMachine calculates aluSheetsCost correctly.
     *
     * Formula: 11.42 * pressSheetSqm * inkCount
     */
    public function test_ctp_machine_calculates_alu_sheets_cost(): void
    {
        $ctp = $this->createCTPMachine();

        // 1020x720mm = 0.7344 sqm
        // 4 colors (CMYK)
        // aluSheetsCost = 11.42 * 0.7344 * 4 = 33.55
        $context = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo = $ctp->prepareTodo($context);

        $expectedAluCost = round(11.42 * 0.7344 * 4, 2);
        $this->assertEqualsWithDelta(
            $expectedAluCost,
            $todo['cost']['aluSheetsCost'],
            0.1,
            "Alu sheets cost should be approximately {$expectedAluCost}"
        );
    }

    /**
     * Test: CTPMachine aluSheetsCost increases with more colors.
     */
    public function test_ctp_machine_alu_cost_increases_with_colors(): void
    {
        $ctp = $this->createCTPMachine();

        $context1Color = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 1,
            inking: ['recto' => ['K'], 'verso' => []],
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $context4Colors = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo1Color = $ctp->prepareTodo($context1Color);
        $todo4Colors = $ctp->prepareTodo($context4Colors);

        $this->assertGreaterThan(
            $todo1Color['cost']['aluSheetsCost'],
            $todo4Colors['cost']['aluSheetsCost'],
            '4 colors should have higher alu sheets cost than 1 color'
        );
    }

    /**
     * Test: CTPMachine aluSheetsCost increases with press sheet size.
     */
    public function test_ctp_machine_alu_cost_increases_with_sheet_size(): void
    {
        $ctp = $this->createCTPMachine();

        $contextSmallSheet = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
            pressSheet: $this->createPressSheet(520, 360, 0.05),
        );

        $contextLargeSheet = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todoSmall = $ctp->prepareTodo($contextSmallSheet);
        $todoLarge = $ctp->prepareTodo($contextLargeSheet);

        $this->assertGreaterThan(
            $todoSmall['cost']['aluSheetsCost'],
            $todoLarge['cost']['aluSheetsCost'],
            'Larger press sheet should have higher alu sheets cost'
        );
    }

    /**
     * Test: CTPMachine includes inking in todo.
     */
    public function test_ctp_machine_includes_inking_in_todo(): void
    {
        $ctp = $this->createCTPMachine();

        $inking = ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => ['K']];
        $context = $this->createTodoContext(
            numberOfCopies: 1000,
            numberOfColors: 5,
            inking: $inking,
            pressSheet: $this->createPressSheet(1020, 720, 0.10),
        );

        $todo = $ctp->prepareTodo($context);

        $this->assertEquals($inking, $todo['inking']);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Create a mock OffsetPrintingPress with standard settings.
     */
    private function createOffsetPrintingPress(): OffsetPrintingPress
    {
        // Create a mock that returns fixed values
        $press = $this->getMockBuilder(OffsetPrintingPress::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseSetupDuration', 'getSetupDurationPerColor', 'getSheetsPerHour', 'getMaxInputStackHeight', 'getStackReplenishmentDuration', 'getCostPerHour'])
            ->getMock();

        $press->method('getBaseSetupDuration')->willReturn(15.0);
        $press->method('getSetupDurationPerColor')->willReturn(5.0);
        $press->method('getSheetsPerHour')->willReturn(10000);
        $press->method('getMaxInputStackHeight')->willReturn(1.0);
        $press->method('getStackReplenishmentDuration')->willReturn(5.0);
        $press->method('getCostPerHour')->willReturn(150.0);

        return $press;
    }

    /**
     * Create a mock CTPMachine with standard settings.
     */
    private function createCTPMachine(): CTPMachine
    {
        $ctp = $this->getMockBuilder(CTPMachine::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSqmPerHour', 'getCostPerHour'])
            ->getMock();

        $ctp->method('getSqmPerHour')->willReturn(50.0);
        $ctp->method('getCostPerHour')->willReturn(52.0);

        return $ctp;
    }

    /**
     * Create a TodoContext for testing.
     */
    private function createTodoContext(
        float $numberOfCopies = 1000,
        float $numberOfColors = 4,
        float $paperWeight = 115,
        array $inking = ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
        ?GridFittingInterface $gridFitting = null,
        ?PressSheet $pressSheet = null,
    ): TodoContext {
        return new TodoContext(
            numberOfCopies: $numberOfCopies,
            numberOfColors: $numberOfColors,
            paperWeight: $paperWeight,
            inking: $inking,
            openPoseDimensions: new Dimensions(300, 200),
            closedPoseDimensions: new Dimensions(300, 200),
            cutSheetCount: 0,
            gridFitting: $gridFitting,
            pressSheet: $pressSheet,
        );
    }

    /**
     * Create a mock GridFitting.
     */
    private function createGridFitting(int $cols = 2, int $rows = 2): GridFittingInterface
    {
        $tiles = [];
        for ($i = 0; $i < $cols * $rows; $i++) {
            $tiles[] = ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100];
        }

        $gridFitting = $this->createMock(GridFittingInterface::class);
        $gridFitting->method('getCols')->willReturn($cols);
        $gridFitting->method('getRows')->willReturn($rows);
        $gridFitting->method('getTiles')->willReturn($tiles);
        $gridFitting->method('isRotated')->willReturn(false);

        return $gridFitting;
    }

    /**
     * Create a PressSheet for testing.
     */
    private function createPressSheet(float $width, float $height, float $price): PressSheet
    {
        $pressSheet = $this->createMock(PressSheet::class);
        $pressSheet->method('getWidth')->willReturn($width);
        $pressSheet->method('getHeight')->willReturn($height);
        $pressSheet->method('getPrice')->willReturn($price);

        return $pressSheet;
    }
}
