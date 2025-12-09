<?php

namespace App\Application\Process\Service;

/**
 * Interface for ranking and selecting best action paths.
 */
interface ActionPathRankerInterface
{
    /**
     * Select and rank best action paths by cost.
     *
     * @param array $actionPaths Raw paths from ActionTree (array of ActionPathNodeInterface[])
     * @param int $limit Maximum paths to return (default 10)
     * @return array Sorted paths (best/lowest cost first), filtered for unique costs
     */
    public function selectBest(array $actionPaths, int $limit = 10): array;

    /**
     * Calculate total cost for an action path.
     *
     * @param array $actionPath Array of ActionPathNodeInterface
     * @return float Total cost
     */
    public function calculateCost(array $actionPath): float;
}
