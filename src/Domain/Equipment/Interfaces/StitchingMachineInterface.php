<?php

namespace App\Domain\Equipment\Interfaces;

use App\Domain\Geometry\Dimensions;

interface StitchingMachineInterface extends MachineInterface
{
    public function setOpenPoseDimensions(Dimensions $dimensions): void;
}