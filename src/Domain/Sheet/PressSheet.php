<?php

namespace App\Domain\Sheet;

use App\Domain\Geometry\GeometryFactory;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Geometry\Interfaces\PositionInterface;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Geometry\RectangleId;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

class PressSheet extends Sheet implements PressSheetInterface
{
    protected RectangleInterface $usableArea;
    protected float $price;

    public function __construct(
        PositionInterface $position,
        DimensionsInterface $dimensions,
        GeometryFactory $geometryFactory,
        ?RectangleId $id,
        float $price,
    )
    {
        parent::__construct($position, $dimensions, $geometryFactory, $id);
        $this->setPrice($price);
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): PressSheet
    {
        $this->price = $price;
        return $this;
    }



}