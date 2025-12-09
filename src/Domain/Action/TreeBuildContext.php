<?php

namespace App\Domain\Action;

use App\Domain\Geometry\Interfaces\DimensionsInterface;

/**
 * Immutable context for building action trees.
 *
 * Contains all the state needed for tree building, replacing the mutable
 * properties on ActionTree.
 */
final class TreeBuildContext
{
    public function __construct(
        private DimensionsInterface $openPoseDimensions,
        private DimensionsInterface $closedPoseDimensions,
        private float $numberOfCopies,
        private float $numberOfColors,
        private float $paperWeight,
        private array $inking,
    ) {}

    public function getOpenPoseDimensions(): DimensionsInterface
    {
        return $this->openPoseDimensions;
    }

    public function getClosedPoseDimensions(): DimensionsInterface
    {
        return $this->closedPoseDimensions;
    }

    public function getNumberOfCopies(): float
    {
        return $this->numberOfCopies;
    }

    public function getNumberOfColors(): float
    {
        return $this->numberOfColors;
    }

    public function getPaperWeight(): float
    {
        return $this->paperWeight;
    }

    public function getInking(): array
    {
        return $this->inking;
    }
}
