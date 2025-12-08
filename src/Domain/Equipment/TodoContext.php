<?php

namespace App\Domain\Equipment;

use App\Domain\Geometry\Interfaces\DimensionsInterface;

/**
 * Context for Machine::prepareTodo() method.
 * Contains all the information a machine needs to prepare its todo array.
 */
readonly class TodoContext
{
    public function __construct(
        public float $numberOfCopies,
        public float $numberOfColors,
        public float $paperWeight,
        public array $inking,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
        public float $cutSheetCount,
    ) {}
}
