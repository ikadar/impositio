<?php

namespace App\Domain\Equipment;

use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Sheet\PrintFactory;

class Machine implements MachineInterface
{
    public function __construct(
        protected string $id,
        protected float $gripMarginSize,
        protected Dimensions $minSheetDimensions,
        protected Dimensions $maxSheetDimensions,
        protected PrintFactory $printFactory,
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
    public function getGripMarginSize(): float
    {
        return $this->gripMarginSize;
    }

    public function getMinSheetDimensions(): Dimensions
    {
        return $this->minSheetDimensions;
    }

    public function getMaxSheetDimensions(): Dimensions
    {
        return $this->maxSheetDimensions;
    }

    public function getMinSheetRectangle(): RectangleInterface
    {
        return $this->printFactory->newRectangle(
            "maxSheet",
            0,
            0,
            $this->getMinSheetDimensions()->getWidth(),
            $this->getMinSheetDimensions()->getHeight()
        );
    }
    public function getMaxSheetRectangle(): RectangleInterface
    {
        return $this->printFactory->newRectangle(
            "maxSheet",
            0,
            0,
            $this->getMaxSheetDimensions()->getWidth(),
            $this->getMaxSheetDimensions()->getHeight()
        );
    }

}