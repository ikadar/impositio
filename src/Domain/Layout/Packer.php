<?php

namespace App\Domain\Layout;

use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Sheet\PrintFactory;

class Packer implements Interfaces\PackerInterface
{

    public function __construct(
        protected PrintFactory $printFactory,
    )
    {
    }

    public function calculateExhaustiveGridFitting(RectangleInterface $boundingArea, RectangleInterface $tileRect, $rotated): array
    {
        $spacing = $this->printFactory->getCutSpacing();

        $maxCols = floor(($boundingArea->getWidth()) / ($tileRect->getWidth() + (2 * $spacing->getHorizontalSpacing())));
        $maxRows = floor(($boundingArea->getHeight()) / ($tileRect->getHeight() + (2 * $spacing->getVerticalSpacing())));

        $gridFittings = [];
        for ($rowIndex = 1; $rowIndex <= $maxRows; $rowIndex++) {
            for ($colIndex = 1; $colIndex <= $maxCols; $colIndex++) {
                $tiles = $this->calculateTiles($colIndex, $rowIndex, $tileRect, $spacing);
                $gridFittings[] = $this->printFactory->newGridFitting($tiles, $colIndex, $rowIndex, $rotated, $tileRect, $spacing);
            }
        }

        return $gridFittings;
    }

    public function calculateTiles(int $cols, int $rows, RectangleInterface $tileRect, CutSpacing $cutSpacing): array
    {

        $tiles = [];
        for ($rowIndex = 0; $rowIndex < $rows; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $cols; $colIndex++) {
                $tiles[] = $this->printFactory->newTile($colIndex, $rowIndex, $cutSpacing, $tileRect);
            }
        }

        return $tiles;
    }

}