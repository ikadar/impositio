<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Equipment\Enrichment\ActionEnrichmentInterface;
use App\Domain\Equipment\Enrichment\CutoutEnrichment;
use App\Domain\Part\PartProductionContext;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

/**
 * Cutout machine for die-cutting operations.
 */
class CutoutMachine extends Machine
{
    public function calculateCost(ActionPathNodeInterface $action): float|array
    {
        $config = $this->equipmentService->loadById($this->getId());
        $costPerHour = $config['cost-per-hour'] ?? 35;
        $piecesPerHour = $config['pieces-per-hour'] ?? 500;
        $setupDuration = $config['setup-duration'] ?? 5;

        $todo = $action->getTodo();
        $numberOfCopies = $todo['numberOfCopies'] ?? 0;

        // Calculate run duration based on pieces per hour
        $runDuration = ($numberOfCopies / $piecesPerHour) * 60; // in minutes

        // Total duration in hours
        $totalHours = ($setupDuration + $runDuration) / 60;

        return $costPerHour * $totalHours;
    }

    /**
     * Calculate enrichment for cutout machine.
     */
    public function calculateEnrichment(
        PartProductionContext $jobContext,
        GridFittingInterface $gridFitting,
        PressSheetInterface $pressSheet,
        float $cutSheetCount,
    ): ActionEnrichmentInterface {
        return new CutoutEnrichment(
            cost: 0.0, // Cost is calculated later by calculateCost()
            cutSheetCount: $cutSheetCount,
            numberOfCopies: $jobContext->numberOfCopies,
            openPoseDimensions: [
                'width' => $jobContext->openPoseDimensions->getWidth(),
                'height' => $jobContext->openPoseDimensions->getHeight(),
            ],
            closedPoseDimensions: [
                'width' => $jobContext->closedPoseDimensions->getWidth(),
                'height' => $jobContext->closedPoseDimensions->getHeight(),
            ],
        );
    }
}
