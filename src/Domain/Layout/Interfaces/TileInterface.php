<?php

namespace App\Domain\Layout\Interfaces;

use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\Tile;

interface TileInterface
{
    public function getGridPosition(): array;
    public function setGridPosition(array $gridPosition): Tile;
    public function getInnerTile(): RectangleInterface;
    public function setInnerTile(RectangleInterface $innerTile): Tile;
    public function getTileWithSpacing(): RectangleInterface;
    public function setTileWithSpacing(RectangleInterface $tileWithSpacing): Tile;
}