<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;

/**
 * Assembler machine for assembly operations.
 *
 * @experimental Will be removed in future version
 */
class Assembler extends Machine
{
    public function calculateCost(ActionPathNodeInterface $action): float|array
    {
        $config = $this->equipmentService->loadById($this->getId());
        $costPerHour = $config['cost-per-hour'] ?? 40;
        $piecesPerHour = $config['pieces-per-hour'] ?? 300;
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
