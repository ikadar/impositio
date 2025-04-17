<?php

namespace App\Domain\Layout\Interfaces;

use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\CutSpacing;

interface PackerInterface
{
    public function calculateExhaustiveGridFitting(RectangleInterface $boundingArea, RectangleInterface $tileRect, CutSpacing $spacing, $rotated): array;

    public function calculateGridFitting($cols, $rows, RectangleInterface $tileRect, CutSpacing $cutSpacing): array;
}