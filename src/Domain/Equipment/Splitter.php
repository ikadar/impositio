<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;

/**
 * Splitter machine for splitting operations.
 *
 * @experimental Will be removed in future version
 */
class Splitter extends Machine
{
    public function calculateCost(ActionPathNodeInterface $action): float|array
    {
        $config = $this->equipmentService->loadById($this->getId());
        $costPerHour = $config['cost-per-hour'] ?? 25;
        $piecesPerHour = $config['pieces-per-hour'] ?? 1000;
        $setupDuration = $config['setup-duration'] ?? 3;

        $todo = $action->getTodo();
        $numberOfCopies = $todo['numberOfCopies'] ?? 0;

        // Calculate run duration based on pieces per hour
        $runDuration = ($numberOfCopies / $piecesPerHour) * 60; // in minutes

        // Total duration in hours
        $totalHours = ($setupDuration + $runDuration) / 60;

        return $costPerHour * $totalHours;
    }
}
