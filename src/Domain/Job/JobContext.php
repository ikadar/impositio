<?php

namespace App\Domain\Job;

use App\Domain\Geometry\Interfaces\DimensionsInterface;

/**
 * Job-level context containing parameters that apply to the entire job.
 *
 * This is the single source of truth for job parameters.
 * These values should NOT be duplicated in individual action nodes.
 *
 * Used by:
 * - Pipeline processors to access job parameters
 * - Machine enrichment calculations
 * - Cost calculations
 */
readonly class JobContext
{
    public function __construct(
        public float $numberOfCopies,
        public float $numberOfColors,
        public float $paperWeight,
        public array $inking,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
    ) {}

    /**
     * Get total ink count (recto + verso).
     */
    public function getTotalInkCount(): int
    {
        $rectoCount = count($this->inking['recto'] ?? []);
        $versoCount = count($this->inking['verso'] ?? []);
        return $rectoCount + $versoCount;
    }

    /**
     * Check if this is a verso (double-sided) print job.
     */
    public function hasVerso(): bool
    {
        return !empty($this->inking['verso']);
    }

    /**
     * Get recto ink count.
     */
    public function getRectoInkCount(): int
    {
        return count($this->inking['recto'] ?? []);
    }

    /**
     * Get verso ink count.
     */
    public function getVersoInkCount(): int
    {
        return count($this->inking['verso'] ?? []);
    }
}
