<?php

namespace App\Domain\Layout;

use App\Domain\Geometry\Interfaces\RectangleInterface;

class Tile implements Interfaces\TileInterface
{

    public function __construct(
        protected array $gridPosition,
        protected RectangleInterface $innerTile,
        protected RectangleInterface $tileWithSpacing,
    )
    {
    }

    public function getGridPosition(): array
    {
        return $this->gridPosition;
    }

    public function setGridPosition(array $gridPosition): Tile
    {
        $this->gridPosition = $gridPosition;
        return $this;
    }

    public function getInnerTile(): RectangleInterface
    {
        return $this->innerTile;
    }

    public function setInnerTile(RectangleInterface $innerTile): Tile
    {
        $this->innerTile = $innerTile;
        return $this;
    }

    public function getTileWithSpacing(): RectangleInterface
    {
        return $this->tileWithSpacing;
    }

    public function setTileWithSpacing(RectangleInterface $tileWithSpacing): Tile
    {
        $this->tileWithSpacing = $tileWithSpacing;
        return $this;
    }

}