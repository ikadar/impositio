<?php

namespace App\Application\Process\DTO;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;

/**
 * DTO representing a processed action path result.
 *
 * Contains the path nodes along with calculated totals.
 */
readonly class ActionPathResult
{
    /**
     * @param ActionPathNodeInterface[] $nodes Action path nodes
     * @param float $totalCost Total cost of the path
     * @param float $totalDuration Total duration (setup + run) in minutes
     * @param string $pressSheetSize Press sheet dimensions as "WxH" string
     */
    public function __construct(
        public array $nodes,
        public float $totalCost,
        public float $totalDuration,
        public string $pressSheetSize,
    ) {}
}
