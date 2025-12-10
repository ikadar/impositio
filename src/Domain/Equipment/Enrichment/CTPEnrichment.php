<?php

namespace App\Domain\Equipment\Enrichment;

/**
 * Enrichment data for CTP (Computer to Plate) machine actions.
 *
 * Contains:
 * - Machine cost (based on duration and hourly rate)
 * - Alu sheets cost (based on press sheet size and ink count)
 * - Cut sheet count
 * - Inking specification
 */
readonly class CTPEnrichment implements ActionEnrichmentInterface
{
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private float $aluSheetsCost,
        private float $numberOfCopies,
        private float $numberOfColors,
        private array $inking,
    ) {}

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getCutSheetCount(): float
    {
        return $this->cutSheetCount;
    }

    public function getAluSheetsCost(): float
    {
        return $this->aluSheetsCost;
    }

    public function getNumberOfCopies(): float
    {
        return $this->numberOfCopies;
    }

    public function getNumberOfColors(): float
    {
        return $this->numberOfColors;
    }

    public function getInking(): array
    {
        return $this->inking;
    }

    public function getCostBreakdown(): array
    {
        return [
            'machineCost' => $this->cost,
            'aluSheetsCost' => $this->aluSheetsCost,
        ];
    }

    /**
     * Convert to array format compatible with existing 'todo' structure.
     */
    public function toArray(): array
    {
        return [
            'numberOfCopies' => $this->numberOfCopies,
            'numberOfColors' => $this->numberOfColors,
            'cutSheetCount' => $this->cutSheetCount,
            'inking' => $this->inking,
            'cost' => [
                'cost' => $this->cost,
                'aluSheetsCost' => $this->aluSheetsCost,
            ],
        ];
    }
}
