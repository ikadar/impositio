<?php

namespace App\Domain\Action\Pipeline;

use App\Domain\Geometry\Interfaces\DimensionsInterface;

/**
 * Parameters needed for extending an action path.
 * Contains all the context information that processors need.
 */
readonly class ExtensionParams
{
    public function __construct(
        public float $numberOfCopies,
        public float $numberOfColors,
        public float $paperWeight,
        public array $inking,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
    ) {}
}
