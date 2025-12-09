<?php

namespace App\Application\Process\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\PartPayload;

/**
 * Interface for transforming action path results to response arrays.
 */
interface ActionPathTransformerInterface
{
    /**
     * Transform ActionPathResult to response array format.
     *
     * @param ActionPathResult $result The action path result
     * @param PartPayload $part The part payload (for requiredParts)
     * @param ActionTreeInput $input The input parameters (for dimensions)
     * @return array Response array with id, designation, nodes, cost, duration, etc.
     */
    public function toResponseArray(
        ActionPathResult $result,
        PartPayload $part,
        ActionTreeInput $input
    ): array;
}
