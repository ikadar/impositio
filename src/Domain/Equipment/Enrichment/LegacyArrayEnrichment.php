<?php

namespace App\Domain\Equipment\Enrichment;

/**
 * Wrapper for legacy todo arrays to implement ActionEnrichmentInterface.
 *
 * This class provides backward compatibility during the transition from
 * array-based todo to typed ActionEnrichmentInterface.
 *
 * @deprecated This class exists only for backward compatibility. Use typed enrichment classes instead.
 */
readonly class LegacyArrayEnrichment implements ActionEnrichmentInterface
{
    /**
     * @param array<string, mixed> $todoArray The legacy todo array
     */
    public function __construct(
        private array $todoArray
    ) {}

    public function getCost(): float
    {
        // Legacy todo arrays have nested 'cost' structure: ['cost' => ['cost' => X, ...]]
        if (isset($this->todoArray['cost']) && is_array($this->todoArray['cost'])) {
            return (float) ($this->todoArray['cost']['cost'] ?? 0);
        }

        // Fallback to direct 'cost' key
        return (float) ($this->todoArray['cost'] ?? 0);
    }

    public function getCutSheetCount(): float
    {
        return (float) ($this->todoArray['cutSheetCount'] ?? 0);
    }

    public function getCostBreakdown(): array
    {
        if (isset($this->todoArray['cost']) && is_array($this->todoArray['cost'])) {
            return $this->todoArray['cost'];
        }

        return [
            'cost' => $this->getCost(),
        ];
    }

    /**
     * Returns the original todo array for backward compatibility.
     */
    public function toArray(): array
    {
        return $this->todoArray;
    }

    /**
     * Get any value from the legacy todo array.
     *
     * @param string $key The key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->todoArray[$key] ?? $default;
    }
}
