<?php

namespace App\Domain\Equipment\Enrichment;

/**
 * Enrichment data for cutting machine actions.
 *
 * Contains:
 * - Machine cost (based on duration and hourly rate)
 * - Number of cuts (total cuts needed)
 * - Trim cuts (cuts to trim excess paper)
 * - Cut cuts (cuts to separate poses)
 */
readonly class CuttingEnrichment implements ActionEnrichmentInterface
{
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private int $numberOfCuts,
        private int $trimCuts,
        private int $cuts,
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

    public function getNumberOfCuts(): int
    {
        return $this->numberOfCuts;
    }

    public function getTrimCuts(): int
    {
        return $this->trimCuts;
    }

    public function getCuts(): int
    {
        return $this->cuts;
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
        ];
    }

    /**
     * Convert to array format compatible with existing 'todo' structure.
     */
    public function toArray(): array
    {
        return [
            'numberOfCuts' => $this->numberOfCuts,
            'numberOfCopies' => $this->numberOfCopies,
            'numberOfColors' => $this->numberOfColors,
            'paperWeight' => $this->paperWeight,
            'cutSheetCount' => $this->cutSheetCount,
            'trimCuts' => $this->trimCuts,
            'cuts' => $this->cuts,
        ];
    }
}
