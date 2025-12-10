<?php

namespace App\Domain\Equipment\Enrichment;

/**
 * Generic enrichment for machines without specific enrichment requirements.
 *
 * Used by: Machine (base), Splitter, Assembler, StitchingMachine, Sechage, etc.
 *
 * Contains basic fields:
 * - numberOfCopies
 * - cutSheetCount
 */
readonly class GenericEnrichment implements ActionEnrichmentInterface
{
    /**
     * @param array<string, mixed> $additionalData Any additional machine-specific data
     */
    public function __construct(
        private float $cost,
        private float $cutSheetCount,
        private float $numberOfCopies,
        private array $additionalData = [],
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

    /**
     * Get any additional machine-specific data.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData;
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
        return array_merge([
            'numberOfCopies' => $this->numberOfCopies,
            'cutSheetCount' => $this->cutSheetCount,
        ], $this->additionalData);
    }
}
