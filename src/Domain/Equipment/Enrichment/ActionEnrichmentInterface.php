<?php

namespace App\Domain\Equipment\Enrichment;

/**
 * Interface for action enrichment data.
 *
 * Each machine type produces enrichment data containing calculated values
 * like cost, duration, and machine-specific metrics.
 *
 * Common fields:
 * - cost: Total cost for this action
 * - cutSheetCount: Number of sheets processed
 *
 * Machine-specific fields are accessed via toArray() or dedicated getters.
 */
interface ActionEnrichmentInterface
{
    /**
     * Get total cost for this action.
     */
    public function getCost(): float;

    /**
     * Get the number of cut sheets (printing sheets) for this action.
     */
    public function getCutSheetCount(): float;

    /**
     * Get cost breakdown with named components.
     *
     * @return array<string, float> Cost breakdown (e.g., ['machineCost' => 50, 'paperCost' => 20])
     */
    public function getCostBreakdown(): array;

    /**
     * Convert to array for JSON serialization.
     *
     * Used for API response and backward compatibility with 'todo' format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
