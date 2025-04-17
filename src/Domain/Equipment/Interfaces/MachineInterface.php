<?php

namespace App\Domain\Equipment\Interfaces;

use App\Domain\Geometry\Dimensions;

interface MachineInterface
{
    public function getId(): string;
    public function getGripMarginSize(): float;

    public function getMinSheetDimensions(): Dimensions;

    public function getMaxSheetDimensions(): Dimensions;

}