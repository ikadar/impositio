<?php

namespace App\Domain\Layout\Interfaces;

use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\CutSpacing;
use App\Domain\Sheet\Interfaces\InputSheetInterface;

interface PackerInterface
{
    public function calculateExhaustiveGridFitting(RectangleInterface $boundingArea, InputSheetInterface $tileRect, bool $rotated): array;

    public function calculateTiles(int $cols, int $rows, InputSheetInterface $tileRect, CutSpacing $cutSpacing): array;
}