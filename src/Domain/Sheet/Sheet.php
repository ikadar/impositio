<?php

namespace App\Domain\Sheet;

use App\Domain\Geometry\GeometryFactory;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Geometry\Interfaces\PositionInterface;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Geometry\Rectangle;
use App\Domain\Geometry\RectangleId;
use App\Domain\Sheet\Interfaces\SheetInterface;

class Sheet extends Rectangle implements SheetInterface
{
    protected RectangleInterface $usableArea;

    public function __construct(
        PositionInterface $position,
        DimensionsInterface $dimensions,
        GeometryFactory $geometryFactory,
        ?RectangleId $id
    )
    {
        parent::__construct($position, $dimensions, $geometryFactory, $id);
        $this->usableArea = $this->geometryFactory->newRectangle(
            "usableArea", 0, 0, $this->getWidth(), $this->getHeight()
        );
        $this->usableArea->placeOnto($this, $this->geometryFactory->newPosition(0, 0));
    }

}