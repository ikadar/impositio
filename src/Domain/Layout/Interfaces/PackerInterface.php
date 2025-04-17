<?php

namespace App\Domain\Layout\Interfaces;

use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\CutSpacing;

interface PackerInterface
{
    public function calculateExhaustiveGridFitting(RectangleInterface $boundingArea, RectangleInterface $tileRect, bool $rotated): array;

    public function calculateTiles(int $cols, int $rows, RectangleInterface $tileRect, CutSpacing $cutSpacing): array;
}