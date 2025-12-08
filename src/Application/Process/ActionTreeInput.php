<?php

namespace App\Application\Process;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

/**
 * DTO containing all parameters required by ActionTree::process().
 */
readonly class ActionTreeInput
{
    /**
     * @param AbstractActionInterface[] $abstractActions
     * @param PressSheetInterface[] $pressSheets
     */
    public function __construct(
        public array $abstractActions,
        public array $pressSheets,
        public InputSheetInterface $zone,
        public DimensionsInterface $openPoseDimensions,
        public DimensionsInterface $closedPoseDimensions,
        public float $numberOfCopies,
        public float $numberOfColors,
        public float $paperWeight,
        public array $inking,
    ) {}
}
