<?php

namespace App\Tests\Unit\Domain\Equipment;

use App\Domain\Equipment\CTPMachine;
use App\Domain\Equipment\MachineType;
use App\Domain\Equipment\OffsetPrintingPress;
use App\Domain\Geometry\Dimensions;
use App\Domain\Job\JobContext;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\PressSheet;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Machine::calculateEnrichment() methods.
 *
 * These tests document and protect the exact cost calculation logic
 * for each machine type.
 */
class MachinePrepareTodoTest extends TestCase
{
    // ==================== OFFSET PRINTING PRESS ====================

    /**
     * Test: OffsetPrintingPress calculateEnrichment returns correct structure.
     */
    public function test_offset_press_prepareTodo_returns_correct_structure(): void
    {
        $press = $this->createOffsetPrintingPress();
        $jobContext = $this->createJobContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
        );
        $gridFitting = $this->createGridFitting(cols: 5, rows: 2);
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        $enrichment = $press->calculateEnrichment($jobContext, $gridFitting, $pressSheet, 0);
        $todo = $enrichment->toArray();

        // Structure assertions
        $this->assertArrayHasKey('numberOfCopies', $todo);
        $this->assertArrayHasKey('numberOfColors', $todo);
        $this->assertArrayHasKey('paperWeight', $todo);
        $this->assertArrayHasKey('cutSheetCount', $todo);
        $this->assertArrayHasKey('cost', $todo);
        $this->assertIsArray($todo['cost']);
        $this->assertArrayHasKey('paperCost', $todo['cost']);
    }

    /**
     * Test: OffsetPrintingPress calculates cutSheetCount correctly.
     */
    public function test_offset_press_calculates_cut_sheet_count(): void
    {
        $press = $this->createOffsetPrintingPress();
        $jobContext = $this->createJobContext(numberOfCopies: 1000, numberOfColors: 4);
        $gridFitting = $this->createGridFitting(cols: 5, rows: 2);
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        // 1000 copies, 10 poses per sheet (5x2) = 100 sheets
        $enrichment = $press->calculateEnrichment($jobContext, $gridFitting, $pressSheet, 0);

        $this->assertEquals(100, $enrichment->getCutSheetCount());
    }

    /**
     * Test: OffsetPrintingPress calculates cutSheetCount with ceiling.
     */
    public function test_offset_press_cut_sheet_count_uses_ceiling(): void
    {
        $press = $this->createOffsetPrintingPress();
        $jobContext = $this->createJobContext(numberOfCopies: 1001, numberOfColors: 4);
        $gridFitting = $this->createGridFitting(cols: 5, rows: 2);
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        // 1001 copies, 10 poses per sheet = 101 sheets (ceiling)
        $enrichment = $press->calculateEnrichment($jobContext, $gridFitting, $pressSheet, 0);

        $this->assertEquals(101, $enrichment->getCutSheetCount());
    }

    /**
     * Test: OffsetPrintingPress calculates paper cost correctly.
     */
    public function test_offset_press_calculates_paper_cost(): void
    {
        $press = $this->createOffsetPrintingPress();
        $jobContext = $this->createJobContext(numberOfCopies: 1000, numberOfColors: 4);
        $gridFitting = $this->createGridFitting(cols: 5, rows: 2);
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        // 1000 copies, 10 poses per sheet, 0.10 EUR per sheet
        // Paper cost per product = 0.10 / 10 = 0.01
        // Total paper cost = 1000 * 0.01 = 10.00
        $enrichment = $press->calculateEnrichment($jobContext, $gridFitting, $pressSheet, 0);
        $todo = $enrichment->toArray();

        $this->assertEqualsWithDelta(10.0, $todo['cost']['paperCost'], 0.01);
    }

    /**
     * Test: OffsetPrintingPress cost increases with number of colors.
     */
    public function test_offset_press_cost_increases_with_colors(): void
    {
        $press = $this->createOffsetPrintingPress();
        $gridFitting = $this->createGridFitting(cols: 5, rows: 2);
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        $jobContext1Color = $this->createJobContext(numberOfCopies: 1000, numberOfColors: 1);
        $jobContext4Colors = $this->createJobContext(numberOfCopies: 1000, numberOfColors: 4);

        $enrichment1Color = $press->calculateEnrichment($jobContext1Color, $gridFitting, $pressSheet, 0);
        $enrichment4Colors = $press->calculateEnrichment($jobContext4Colors, $gridFitting, $pressSheet, 0);

        $this->assertGreaterThan(
            $enrichment1Color->getCost(),
            $enrichment4Colors->getCost(),
            '4 colors should cost more than 1 color'
        );
    }

    // ==================== CTP MACHINE ====================

    /**
     * Test: CTPMachine calculateEnrichment returns correct structure.
     */
    public function test_ctp_machine_prepareTodo_returns_correct_structure(): void
    {
        $ctp = $this->createCTPMachine();
        $jobContext = $this->createJobContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
        );
        $gridFitting = $this->createGridFitting();
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        $enrichment = $ctp->calculateEnrichment($jobContext, $gridFitting, $pressSheet, 1000);
        $todo = $enrichment->toArray();

        // Structure assertions
        $this->assertArrayHasKey('numberOfCopies', $todo);
        $this->assertArrayHasKey('numberOfColors', $todo);
        $this->assertArrayHasKey('cutSheetCount', $todo);
        $this->assertArrayHasKey('inking', $todo);
        $this->assertArrayHasKey('cost', $todo);
        $this->assertIsArray($todo['cost']);
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
        $jobContext = $this->createJobContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
        );
        $gridFitting = $this->createGridFitting();
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        // 1020x720mm = 0.7344 sqm
        // 4 colors (CMYK)
        // aluSheetsCost = 11.42 * 0.7344 * 4 = 33.55
        $enrichment = $ctp->calculateEnrichment($jobContext, $gridFitting, $pressSheet, 1000);
        $todo = $enrichment->toArray();

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
        $gridFitting = $this->createGridFitting();
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        $jobContext1Color = $this->createJobContext(
            numberOfCopies: 1000,
            numberOfColors: 1,
            inking: ['recto' => ['K'], 'verso' => []],
        );
        $jobContext4Colors = $this->createJobContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
        );

        $enrichment1Color = $ctp->calculateEnrichment($jobContext1Color, $gridFitting, $pressSheet, 1000);
        $enrichment4Colors = $ctp->calculateEnrichment($jobContext4Colors, $gridFitting, $pressSheet, 1000);

        $todo1Color = $enrichment1Color->toArray();
        $todo4Colors = $enrichment4Colors->toArray();

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
        $gridFitting = $this->createGridFitting();
        $jobContext = $this->createJobContext(
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
        );

        $smallSheet = $this->createPressSheet(520, 360, 0.05);
        $largeSheet = $this->createPressSheet(1020, 720, 0.10);

        $enrichmentSmall = $ctp->calculateEnrichment($jobContext, $gridFitting, $smallSheet, 1000);
        $enrichmentLarge = $ctp->calculateEnrichment($jobContext, $gridFitting, $largeSheet, 1000);

        $todoSmall = $enrichmentSmall->toArray();
        $todoLarge = $enrichmentLarge->toArray();

        $this->assertGreaterThan(
            $todoSmall['cost']['aluSheetsCost'],
            $todoLarge['cost']['aluSheetsCost'],
            'Larger press sheet should have higher alu sheets cost'
        );
    }

    /**
     * Test: CTPMachine includes inking in enrichment.
     */
    public function test_ctp_machine_includes_inking_in_todo(): void
    {
        $ctp = $this->createCTPMachine();
        $gridFitting = $this->createGridFitting();
        $pressSheet = $this->createPressSheet(1020, 720, 0.10);

        $inking = ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => ['K']];
        $jobContext = $this->createJobContext(
            numberOfCopies: 1000,
            numberOfColors: 5,
            inking: $inking,
        );

        $enrichment = $ctp->calculateEnrichment($jobContext, $gridFitting, $pressSheet, 1000);
        $todo = $enrichment->toArray();

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
     * Create a JobContext for testing.
     */
    private function createJobContext(
        float $numberOfCopies = 1000,
        float $numberOfColors = 4,
        float $paperWeight = 115,
        array $inking = ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
    ): JobContext {
        return new JobContext(
            numberOfCopies: $numberOfCopies,
            numberOfColors: $numberOfColors,
            paperWeight: $paperWeight,
            inking: $inking,
            openPoseDimensions: new Dimensions(300, 200),
            closedPoseDimensions: new Dimensions(300, 200),
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
