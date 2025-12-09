<?php

namespace App\Application\Process\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\PartPayload;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Service\ActionParamsExtractorInterface;

/**
 * Production planning service.
 *
 * Responsibility: Execute ActionTree processing and return ranked results.
 */
class ProductionPlanningService implements ProductionPlanningServiceInterface
{
    public function __construct(
        private ActionTreeInterface $actionTree,
        private ActionParamsExtractorInterface $paramsExtractor,
        private ActionPathRankerInterface $ranker,
    ) {}

    /**
     * Execute production planning for a part.
     *
     * @param PartPayload $part The part to plan
     * @param array $pressSheets Available press sheets
     * @return ActionPathResult[] Ranked action paths
     */
    public function plan(PartPayload $part, array $pressSheets): array
    {
        // Extract ActionTree input
        $input = $this->paramsExtractor->extractForActionTree($part, $pressSheets);

        if ($input === null) {
            return [];
        }

        try {
            // Execute ActionTree
            $rawPaths = $this->actionTree->process(
                $input->abstractActions,
                $input->pressSheets,
                $input->zone,
                $input->openPoseDimensions,
                $input->closedPoseDimensions,
                $input->numberOfCopies,
                $input->numberOfColors,
                $input->paperWeight,
                $input->inking,
            );

            // Rank and select best paths
            $bestPaths = $this->ranker->selectBest($rawPaths);

            // Convert to ActionPathResult DTOs
            return $this->toResults($bestPaths);
        } catch (\Throwable $e) {
            // Log but don't fail - return empty array
            error_log("Production planning failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get ActionTree input for a part.
     */
    public function getInput(PartPayload $part, array $pressSheets): ?ActionTreeInput
    {
        return $this->paramsExtractor->extractForActionTree($part, $pressSheets);
    }

    /**
     * Convert raw paths to ActionPathResult DTOs.
     *
     * @param array $paths Array of action paths
     * @return ActionPathResult[]
     */
    private function toResults(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            $results[] = new ActionPathResult(
                nodes: $path,
                totalCost: $this->ranker->calculateCost($path),
                totalDuration: $this->calculateDuration($path),
                pressSheetSize: $this->extractPressSheetSize($path),
            );
        }

        return $results;
    }

    /**
     * Calculate total duration for a path.
     */
    private function calculateDuration(array $path): float
    {
        $duration = 0.0;

        foreach ($path as $node) {
            $duration += $node->calculateSetupDuration() + $node->calculateRunDuration();
        }

        return $duration;
    }

    /**
     * Extract press sheet size from path.
     */
    private function extractPressSheetSize(array $path): string
    {
        if (empty($path)) {
            return '';
        }

        $firstNode = $path[0];
        $pressSheet = $firstNode->getPressSheet();

        return sprintf(
            "%dx%d",
            (int) $pressSheet->getWidth(),
            (int) $pressSheet->getHeight()
        );
    }
}
