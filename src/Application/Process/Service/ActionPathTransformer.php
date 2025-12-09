<?php

namespace App\Application\Process\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\PartPayload;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Transforms action path results to response array format.
 *
 * Responsibility: Convert ActionPathResult DTOs to JSON-serializable arrays.
 */
class ActionPathTransformer implements ActionPathTransformerInterface
{
    /**
     * Transform ActionPathResult to response array format.
     */
    public function toResponseArray(
        ActionPathResult $result,
        PartPayload $part,
        ActionTreeInput $input
    ): array {
        $nodes = [];
        $designation = [];
        $cost = 0.0;
        $duration = 0.0;

        $pose = [
            'width' => $input->closedPoseDimensions->getWidth(),
            'height' => $input->closedPoseDimensions->getHeight(),
        ];

        foreach ($result->nodes as $node) {
            $nodeArray = $node->toArray($node->getMachine(), $node->getPressSheet(), $pose);
            $nodeArray = $this->normalizeNodeCost($nodeArray, $cost);

            $cost += $nodeArray['cost'];
            $duration += ($nodeArray['setupDuration'] ?? 0) + ($nodeArray['runDuration'] ?? 0);
            $designation[] = $nodeArray['machine'] ?? '';
            $nodes[] = $nodeArray;
        }

        return [
            'id' => Uuid::v4()->toString(),
            'designation' => $this->buildDesignation($result->pressSheetSize, $designation, $cost, $duration),
            'nodes' => $nodes,
            'cost' => $cost,
            'duration' => $duration,
            'pressSheet' => sprintf("%smm", $result->pressSheetSize),
            'openPoseDimensions' => $this->formatDimensions($input->openPoseDimensions),
            'closedPoseDimensions' => $this->formatDimensions($input->closedPoseDimensions),
            'requiredParts' => $part->requiredParts,
        ];
    }

    /**
     * Normalize node cost - handle array format and sum additional costs.
     *
     * @param array $nodeArray Node array with cost
     * @param float &$additionalCost Reference to accumulate additional costs
     * @return array Node array with normalized cost
     */
    private function normalizeNodeCost(array $nodeArray, float &$additionalCost): array
    {
        if (is_array($nodeArray['cost'])) {
            $baseCost = $nodeArray['cost']['cost'] ?? 0;
            foreach ($nodeArray['cost'] as $costName => $costValue) {
                if ($costName !== 'cost') {
                    $additionalCost += $costValue;
                }
            }
            $nodeArray['cost'] = $baseCost;
        }

        return $nodeArray;
    }

    /**
     * Build designation string for the action path.
     */
    private function buildDesignation(string $pressSheet, array $machines, float $cost, float $duration): string
    {
        return sprintf(
            "(%s) %s Cost: %s€; Duration: %smin",
            $pressSheet,
            implode(" > ", array_filter($machines)),
            round($cost, 2),
            round($duration, 2)
        );
    }

    /**
     * Format dimensions as "WxH" string.
     */
    private function formatDimensions(DimensionsInterface $dimensions): string
    {
        return sprintf(
            "%dx%d",
            (int) $dimensions->getWidth(),
            (int) $dimensions->getHeight()
        );
    }
}
