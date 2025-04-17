<?php

namespace App\Domain\Sheet\Interfaces;

use App\Domain\Geometry\Direction;
use App\Domain\Sheet\GripMarginPosition;

interface InputSheetInterface extends SheetInterface
{
    public function setGripMarginSize(float $size): static;
    public function getGripMarginSize(): float;

    public function getGripMarginPosition(): GripMarginPosition;
}