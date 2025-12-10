<?php

namespace App\Domain\Part;

use App\Domain\Geometry\Interfaces\DimensionsInterface;

/**
 * Part-level production context containing parameters for manufacturing a single part.
 *
 * This is the single source of truth for part production parameters.
 * These values apply to a part being processed, NOT to a job or order.
 *
 * Note: inking and numberOfColors are kept for backward compatibility.
 * For new code, use PrintActionParams from action nodes for inking info.
 *
 * Used by:
 * - Action tree building (via ActionTreeBuilder, ActionTreeProcessor)
 * - Pipeline processors to access part parameters
 * - Machine enrichment calculations
 * - Cost calculations
 *
 * Replaces both JobContext and TreeBuildContext.
 */
readonly class PartProductionContext
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
     *
     * @deprecated Use PrintActionParams::getTotalInkCount() from action nodes instead.
     */
    public function getTotalInkCount(): int
    {
        $rectoCount = count($this->inking['recto'] ?? []);
        $versoCount = count($this->inking['verso'] ?? []);
        return $rectoCount + $versoCount;
    }

    /**
     * Check if this is a verso (double-sided) print job.
     *
     * @deprecated Use PrintActionParams::hasVerso() from action nodes instead.
     */
    public function hasVerso(): bool
    {
        return !empty($this->inking['verso']);
    }

    /**
     * Get recto ink count.
     *
     * @deprecated Use PrintActionParams::getRectoInkCount() from action nodes instead.
     */
    public function getRectoInkCount(): int
    {
        return count($this->inking['recto'] ?? []);
    }

    /**
     * Get verso ink count.
     *
     * @deprecated Use PrintActionParams::getVersoInkCount() from action nodes instead.
     */
    public function getVersoInkCount(): int
    {
        return count($this->inking['verso'] ?? []);
    }

    // Getter methods for compatibility with code expecting method calls

    public function getOpenPoseDimensions(): DimensionsInterface
    {
        return $this->openPoseDimensions;
    }

    public function getClosedPoseDimensions(): DimensionsInterface
    {
        return $this->closedPoseDimensions;
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

    public function getInking(): array
    {
        return $this->inking;
    }
}
