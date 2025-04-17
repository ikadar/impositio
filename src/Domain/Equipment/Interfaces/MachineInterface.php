<?php

namespace App\Domain\Equipment\Interfaces;

use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;

interface MachineInterface
{
    public function getId(): string;
    public function getGripMarginSize(): float;
    public function getMinSheetDimensions(): Dimensions;
    public function getMaxSheetDimensions(): Dimensions;
    public function getMinSheetRectangle(): RectangleInterface;
    public function getMaxSheetRectangle(): RectangleInterface;

}