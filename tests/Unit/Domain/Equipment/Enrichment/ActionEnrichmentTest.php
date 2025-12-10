<?php

namespace App\Tests\Unit\Domain\Equipment\Enrichment;

use App\Domain\Equipment\Enrichment\ActionEnrichmentInterface;
use App\Domain\Equipment\Enrichment\CTPEnrichment;
use App\Domain\Equipment\Enrichment\CutoutEnrichment;
use App\Domain\Equipment\Enrichment\CuttingEnrichment;
use App\Domain\Equipment\Enrichment\FolderEnrichment;
use App\Domain\Equipment\Enrichment\GenericEnrichment;
use App\Domain\Equipment\Enrichment\OffsetPressEnrichment;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ActionEnrichment classes.
 *
 * Tests:
 * - All enrichment classes implement ActionEnrichmentInterface
 * - Correct values are returned from getters
 * - toArray() produces backward-compatible format
 * - getCostBreakdown() returns proper structure
 */
class ActionEnrichmentTest extends TestCase
{
    // ==================== OffsetPressEnrichment Tests ====================

    public function test_offset_press_enrichment_implements_interface(): void
    {
        $enrichment = new OffsetPressEnrichment(
            cost: 50.0,
            cutSheetCount: 100,
            paperCost: 10.0,
            numberOfCopies: 1000,
            numberOfColors: 4,
            paperWeight: 115,
        );

        $this->assertInstanceOf(ActionEnrichmentInterface::class, $enrichment);
    }

    public function test_offset_press_enrichment_returns_correct_values(): void
    {
        $enrichment = new OffsetPressEnrichment(
            cost: 51.87,
            cutSheetCount: 100,
            paperCost: 10.13,
            numberOfCopies: 1000,
            numberOfColors: 4,
            paperWeight: 115,
        );

        $this->assertEquals(51.87, $enrichment->getCost());
        $this->assertEquals(100, $enrichment->getCutSheetCount());
        $this->assertEquals(10.13, $enrichment->getPaperCost());
        $this->assertEquals(1000, $enrichment->getNumberOfCopies());
        $this->assertEquals(4, $enrichment->getNumberOfColors());
        $this->assertEquals(115, $enrichment->getPaperWeight());
    }

    public function test_offset_press_enrichment_cost_breakdown(): void
    {
        $enrichment = new OffsetPressEnrichment(
            cost: 51.87,
            cutSheetCount: 100,
            paperCost: 10.13,
            numberOfCopies: 1000,
            numberOfColors: 4,
            paperWeight: 115,
        );

        $breakdown = $enrichment->getCostBreakdown();

        $this->assertArrayHasKey('machineCost', $breakdown);
        $this->assertArrayHasKey('paperCost', $breakdown);
        $this->assertEquals(51.87, $breakdown['machineCost']);
        $this->assertEquals(10.13, $breakdown['paperCost']);
    }

    public function test_offset_press_enrichment_to_array(): void
    {
        $enrichment = new OffsetPressEnrichment(
            cost: 51.87,
            cutSheetCount: 100,
            paperCost: 10.13,
            numberOfCopies: 1000,
            numberOfColors: 4,
            paperWeight: 115,
        );

        $array = $enrichment->toArray();

        $this->assertArrayHasKey('numberOfCopies', $array);
        $this->assertArrayHasKey('numberOfColors', $array);
        $this->assertArrayHasKey('paperWeight', $array);
        $this->assertArrayHasKey('cutSheetCount', $array);
        $this->assertArrayHasKey('cost', $array);
        $this->assertIsArray($array['cost']);
        $this->assertEquals(51.87, $array['cost']['cost']);
        $this->assertEquals(10.13, $array['cost']['paperCost']);
    }

    // ==================== CTPEnrichment Tests ====================

    public function test_ctp_enrichment_implements_interface(): void
    {
        $enrichment = new CTPEnrichment(
            cost: 6.17,
            cutSheetCount: 100,
            aluSheetsCost: 33.55,
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
        );

        $this->assertInstanceOf(ActionEnrichmentInterface::class, $enrichment);
    }

    public function test_ctp_enrichment_returns_correct_values(): void
    {
        $inking = ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []];
        $enrichment = new CTPEnrichment(
            cost: 6.17,
            cutSheetCount: 100,
            aluSheetsCost: 33.55,
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: $inking,
        );

        $this->assertEquals(6.17, $enrichment->getCost());
        $this->assertEquals(100, $enrichment->getCutSheetCount());
        $this->assertEquals(33.55, $enrichment->getAluSheetsCost());
        $this->assertEquals(1000, $enrichment->getNumberOfCopies());
        $this->assertEquals(4, $enrichment->getNumberOfColors());
        $this->assertEquals($inking, $enrichment->getInking());
    }

    public function test_ctp_enrichment_cost_breakdown(): void
    {
        $enrichment = new CTPEnrichment(
            cost: 6.17,
            cutSheetCount: 100,
            aluSheetsCost: 33.55,
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []],
        );

        $breakdown = $enrichment->getCostBreakdown();

        $this->assertArrayHasKey('machineCost', $breakdown);
        $this->assertArrayHasKey('aluSheetsCost', $breakdown);
        $this->assertEquals(6.17, $breakdown['machineCost']);
        $this->assertEquals(33.55, $breakdown['aluSheetsCost']);
    }

    public function test_ctp_enrichment_to_array(): void
    {
        $inking = ['recto' => ['C', 'M', 'Y', 'K'], 'verso' => []];
        $enrichment = new CTPEnrichment(
            cost: 6.17,
            cutSheetCount: 100,
            aluSheetsCost: 33.55,
            numberOfCopies: 1000,
            numberOfColors: 4,
            inking: $inking,
        );

        $array = $enrichment->toArray();

        $this->assertArrayHasKey('numberOfCopies', $array);
        $this->assertArrayHasKey('numberOfColors', $array);
        $this->assertArrayHasKey('cutSheetCount', $array);
        $this->assertArrayHasKey('inking', $array);
        $this->assertArrayHasKey('cost', $array);
        $this->assertEquals(6.17, $array['cost']['cost']);
        $this->assertEquals(33.55, $array['cost']['aluSheetsCost']);
    }

    // ==================== CuttingEnrichment Tests ====================

    public function test_cutting_enrichment_implements_interface(): void
    {
        $enrichment = new CuttingEnrichment(
            cost: 5.0,
            cutSheetCount: 100,
            numberOfCuts: 4,
            trimCuts: 2,
            cuts: 2,
            numberOfCopies: 1000,
            numberOfColors: 4,
            paperWeight: 115,
        );

        $this->assertInstanceOf(ActionEnrichmentInterface::class, $enrichment);
    }

    public function test_cutting_enrichment_returns_correct_values(): void
    {
        $enrichment = new CuttingEnrichment(
            cost: 5.0,
            cutSheetCount: 100,
            numberOfCuts: 4,
            trimCuts: 2,
            cuts: 2,
            numberOfCopies: 1000,
            numberOfColors: 4,
            paperWeight: 115,
        );

        $this->assertEquals(5.0, $enrichment->getCost());
        $this->assertEquals(100, $enrichment->getCutSheetCount());
        $this->assertEquals(4, $enrichment->getNumberOfCuts());
        $this->assertEquals(2, $enrichment->getTrimCuts());
        $this->assertEquals(2, $enrichment->getCuts());
    }

    public function test_cutting_enrichment_to_array(): void
    {
        $enrichment = new CuttingEnrichment(
            cost: 5.0,
            cutSheetCount: 100,
            numberOfCuts: 4,
            trimCuts: 2,
            cuts: 2,
            numberOfCopies: 1000,
            numberOfColors: 4,
            paperWeight: 115,
        );

        $array = $enrichment->toArray();

        $this->assertArrayHasKey('numberOfCuts', $array);
        $this->assertArrayHasKey('trimCuts', $array);
        $this->assertArrayHasKey('cuts', $array);
        $this->assertArrayHasKey('cutSheetCount', $array);
        $this->assertEquals(4, $array['numberOfCuts']);
        $this->assertEquals(2, $array['trimCuts']);
        $this->assertEquals(2, $array['cuts']);
    }

    // ==================== GenericEnrichment Tests ====================

    public function test_generic_enrichment_implements_interface(): void
    {
        $enrichment = new GenericEnrichment(
            cost: 10.0,
            cutSheetCount: 100,
            numberOfCopies: 1000,
        );

        $this->assertInstanceOf(ActionEnrichmentInterface::class, $enrichment);
    }

    public function test_generic_enrichment_with_additional_data(): void
    {
        $additionalData = ['customField' => 'value', 'anotherField' => 42];
        $enrichment = new GenericEnrichment(
            cost: 10.0,
            cutSheetCount: 100,
            numberOfCopies: 1000,
            additionalData: $additionalData,
        );

        $this->assertEquals($additionalData, $enrichment->getAdditionalData());

        $array = $enrichment->toArray();
        $this->assertArrayHasKey('customField', $array);
        $this->assertArrayHasKey('anotherField', $array);
        $this->assertEquals('value', $array['customField']);
        $this->assertEquals(42, $array['anotherField']);
    }

    // ==================== FolderEnrichment Tests ====================

    public function test_folder_enrichment_implements_interface(): void
    {
        $enrichment = new FolderEnrichment(
            cost: 15.0,
            cutSheetCount: 100,
            numberOfCopies: 1000,
            inputSheetLength: 0.297,
            openPoseDimensions: ['width' => 297, 'height' => 420],
            closedPoseDimensions: ['width' => 297, 'height' => 210],
        );

        $this->assertInstanceOf(ActionEnrichmentInterface::class, $enrichment);
    }

    public function test_folder_enrichment_returns_correct_values(): void
    {
        $enrichment = new FolderEnrichment(
            cost: 15.0,
            cutSheetCount: 100,
            numberOfCopies: 1000,
            inputSheetLength: 0.297,
            openPoseDimensions: ['width' => 297, 'height' => 420],
            closedPoseDimensions: ['width' => 297, 'height' => 210],
        );

        $this->assertEquals(15.0, $enrichment->getCost());
        $this->assertEquals(100, $enrichment->getCutSheetCount());
        $this->assertEquals(1000, $enrichment->getNumberOfCopies());
        $this->assertEquals(0.297, $enrichment->getInputSheetLength());
        $this->assertEquals(['width' => 297, 'height' => 420], $enrichment->getOpenPoseDimensions());
        $this->assertEquals(['width' => 297, 'height' => 210], $enrichment->getClosedPoseDimensions());
    }

    public function test_folder_enrichment_to_array(): void
    {
        $enrichment = new FolderEnrichment(
            cost: 15.0,
            cutSheetCount: 100,
            numberOfCopies: 1000,
            inputSheetLength: 0.297,
            openPoseDimensions: ['width' => 297, 'height' => 420],
            closedPoseDimensions: ['width' => 297, 'height' => 210],
        );

        $array = $enrichment->toArray();

        $this->assertArrayHasKey('openPoseDimensions', $array);
        $this->assertArrayHasKey('closedPoseDimensions', $array);
        $this->assertArrayHasKey('inputSheetLength', $array);
        $this->assertArrayHasKey('cutSheetCount', $array);
        $this->assertArrayHasKey('numberOfCopies', $array);
    }

    // ==================== CutoutEnrichment Tests ====================

    public function test_cutout_enrichment_implements_interface(): void
    {
        $enrichment = new CutoutEnrichment(
            cost: 20.0,
            cutSheetCount: 100,
            numberOfCopies: 1000,
            openPoseDimensions: ['width' => 300, 'height' => 200],
            closedPoseDimensions: ['width' => 300, 'height' => 200],
        );

        $this->assertInstanceOf(ActionEnrichmentInterface::class, $enrichment);
    }

    public function test_cutout_enrichment_to_array(): void
    {
        $enrichment = new CutoutEnrichment(
            cost: 20.0,
            cutSheetCount: 100,
            numberOfCopies: 1000,
            openPoseDimensions: ['width' => 300, 'height' => 200],
            closedPoseDimensions: ['width' => 300, 'height' => 200],
        );

        $array = $enrichment->toArray();

        $this->assertArrayHasKey('numberOfCopies', $array);
        $this->assertArrayHasKey('cutSheetCount', $array);
        $this->assertArrayHasKey('openPoseDimensions', $array);
        $this->assertArrayHasKey('closedPoseDimensions', $array);
    }
}
