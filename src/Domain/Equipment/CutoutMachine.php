<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;

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
}
