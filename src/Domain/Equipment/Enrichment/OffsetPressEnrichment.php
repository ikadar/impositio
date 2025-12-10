<?php

namespace App\Domain\Equipment\Enrichment;

/**
 * Enrichment data for offset printing press actions.
 *
 * Contains:
 * - Machine cost (based on duration and hourly rate)
 * - Paper cost (based on copies and sheet price)
 * - Cut sheet count (number of printing sheets needed)
 */
readonly class OffsetPressEnrichment implements ActionEnrichmentInterface
{
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private float $paperCost,
        private float $numberOfCopies,
        private float $numberOfColors,
        private float $paperWeight,
    ) {}

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getCutSheetCount(): float
    {
        return $this->cutSheetCount;
    }

    public function getPaperCost(): float
    {
        return $this->paperCost;
    }

    public function getNumberOfCopies(): float
    {
        return $this->numberOfCopies;
    }

    public function getNumberOfColors(): float
    {
        return $this->numberOfColors;
    }

    public function getPaperWeight(): float
    {
        return $this->paperWeight;
    }

    public function getCostBreakdown(): array
    {
        return [
            'machineCost' => $this->cost,
            'paperCost' => $this->paperCost,
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
            'paperWeight' => $this->paperWeight,
            'cutSheetCount' => $this->cutSheetCount,
            'cost' => [
                'cost' => $this->cost,
                'paperCost' => $this->paperCost,
            ],
        ];
    }
}
