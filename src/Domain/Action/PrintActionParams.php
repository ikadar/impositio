<?php

namespace App\Domain\Action;

/**
 * Parameters specific to a print action.
 *
 * Each print action has its own inking specification.
 * This is NOT part-level data - different print actions
 * in the same part can have different inkings.
 *
 * This class extracts inking-related logic from JobContext/TreeBuildContext
 * to the action level where it belongs.
 */
readonly class PrintActionParams
{
    public function __construct(
        public array $inking,  // ['recto' => [...], 'verso' => [...]]
    ) {}

    public function getRectoInks(): array
    {
        return $this->inking['recto'] ?? [];
    }

    public function getVersoInks(): array
    {
        return $this->inking['verso'] ?? [];
    }

    public function hasVerso(): bool
    {
        return !empty($this->getVersoInks());
    }

    public function getRectoInkCount(): int
    {
        return count($this->getRectoInks());
    }

    public function getVersoInkCount(): int
    {
        return count($this->getVersoInks());
    }

    public function getTotalInkCount(): int
    {
        return $this->getRectoInkCount() + $this->getVersoInkCount();
    }

    /**
     * Get the maximum number of colors on either side.
     * Used for machine color capacity filtering.
     */
    public function getMaxColorsPerSide(): int
    {
        return max($this->getRectoInkCount(), $this->getVersoInkCount());
    }

    /**
     * Get inking for a specific side (for verso print action).
     */
    public function getInkingForSide(string $side): array
    {
        return $this->inking[$side] ?? [];
    }
}
