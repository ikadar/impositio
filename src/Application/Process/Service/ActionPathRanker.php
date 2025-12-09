<?php

namespace App\Application\Process\Service;

/**
 * Ranks and selects best action paths based on cost.
 *
 * Responsibility: Select top N action paths by cost, filtering duplicate costs.
 */
class ActionPathRanker implements ActionPathRankerInterface
{
    /**
     * Select and rank best action paths by cost.
     *
     * @param array $actionPaths Raw paths from ActionTree
     * @param int $limit Maximum paths to return
     * @return array Sorted paths (lowest cost first)
     */
    public function selectBest(array $actionPaths, int $limit = 10): array
    {
        if (empty($actionPaths)) {
            return [];
        }

        // Calculate costs for all paths
        $pathsWithCost = [];
        foreach ($actionPaths as $actionPath) {
            $cost = $this->calculateCost($actionPath);
            $pathsWithCost[] = ['path' => $actionPath, 'cost' => $cost];
        }

        // Sort by cost ascending
        usort($pathsWithCost, fn($a, $b) => $a['cost'] <=> $b['cost']);

        // Filter unique costs and limit
        $uniqueCosts = [];
        $result = [];
        foreach ($pathsWithCost as $item) {
            if (!in_array($item['cost'], $uniqueCosts, true)) {
                $uniqueCosts[] = $item['cost'];
                $result[] = $item['path'];
            }
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * Calculate total cost for an action path.
     *
     * Handles both simple cost values and array cost format.
     *
     * @param array $actionPath Array of ActionPathNodeInterface
     * @return float Total cost
     */
    public function calculateCost(array $actionPath): float
    {
        $cost = 0.0;

        foreach ($actionPath as $node) {
            $todo = $node->getTodo();
            $nodeCost = $todo['cost'] ?? 0;

            // Handle array cost format: ['cost' => 100, 'paper' => 20, ...]
            if (is_array($nodeCost)) {
                $nodeCost = $nodeCost['cost'] ?? 0;
            }

            $cost += (float) $nodeCost;
        }

        return $cost;
    }
}
