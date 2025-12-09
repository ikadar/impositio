<?php

namespace App\Domain\Action\Interfaces;

use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

/**
 * Interface for action path nodes.
 *
 * An ActionPathNode represents a single step in a production path,
 * containing the machine, press sheet, zone, grid fitting, and todo information.
 */
interface ActionPathNodeInterface
{
    /**
     * Get the machine assigned to this action.
     */
    public function getMachine(): MachineInterface;

    /**
     * Get the press sheet used for this action.
     */
    public function getPressSheet(): PressSheetInterface;

    /**
     * Get the zone (input sheet) for this action.
     */
    public function getZone(): InputSheetInterface;

    /**
     * Get the grid fitting configuration.
     */
    public function getGridFitting(): GridFittingInterface;

    /**
     * Get the todo/work specification for this action.
     */
    public function getTodo(): array;

    /**
     * Set the todo/work specification.
     */
    public function setTodo(array $todo): static;

    /**
     * Calculate the cost of this action.
     *
     * @return float|array Cost value or detailed cost breakdown
     */
    public function calculateCost(): float|array;

    /**
     * Calculate the setup duration for this action.
     *
     * @return float Duration in minutes
     */
    public function calculateSetupDuration(): float;

    /**
     * Calculate the run duration for this action.
     *
     * @return float Duration in minutes
     */
    public function calculateRunDuration(): float;

    /**
     * Convert the action node to an array representation.
     *
     * @param mixed $machine Machine context
     * @param mixed $pressSheet Press sheet context
     * @param mixed $pose Pose context
     * @return array Array representation of the node
     */
    public function toArray(mixed $machine, mixed $pressSheet, mixed $pose): array;
}
