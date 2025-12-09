<?php

namespace App\Application\Process\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\PartPayload;

/**
 * Interface for production planning service.
 */
interface ProductionPlanningServiceInterface
{
    /**
     * Execute production planning for a part.
     *
     * @param PartPayload $part The part to plan
     * @param array $pressSheets Available press sheets
     * @return ActionPathResult[] Ranked action paths
     */
    public function plan(PartPayload $part, array $pressSheets): array;

    /**
     * Get ActionTree input for a part.
     *
     * @param PartPayload $part The part
     * @param array $pressSheets Available press sheets
     * @return ActionTreeInput|null Input DTO or null if extraction fails
     */
    public function getInput(PartPayload $part, array $pressSheets): ?ActionTreeInput;
}
