<?php

namespace App\Domain\Sheet\Interfaces;

use App\Domain\Geometry\Interfaces\GeometryFactoryInterface;

interface PrintFactoryInterface extends GeometryFactoryInterface
{
    public function newSheet (string $id, float $x, float $y, float $width, float $height): SheetInterface;

    public function newInputSheet (string $id, float $x, float $y, float $width, float $height): InputSheetInterface;
}