<?php

namespace App\Domain\Equipment\Enrichment;

/**
 * Enrichment data for cutout machine actions.
 *
 * Contains:
 * - Machine cost
 * - Cut sheet count
 * - Number of copies
 * - Open/closed pose dimensions
 */
readonly class CutoutEnrichment implements ActionEnrichmentInterface
{
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private float $numberOfCopies,
        private array $openPoseDimensions,
        private array $closedPoseDimensions,
    ) {}

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getCutSheetCount(): float
    {
        return $this->cutSheetCount;
    }

    public function getNumberOfCopies(): float
    {
        return $this->numberOfCopies;
    }

    public function getOpenPoseDimensions(): array
    {
        return $this->openPoseDimensions;
    }

    public function getClosedPoseDimensions(): array
    {
        return $this->closedPoseDimensions;
    }

    public function getCostBreakdown(): array
    {
        return [
            'machineCost' => $this->cost,
        ];
    }

    /**
     * Convert to array format compatible with existing 'todo' structure.
     */
    public function toArray(): array
    {
        return [
            'numberOfCopies' => $this->numberOfCopies,
            'cutSheetCount' => $this->cutSheetCount,
            'openPoseDimensions' => $this->openPoseDimensions,
            'closedPoseDimensions' => $this->closedPoseDimensions,
        ];
    }
}
