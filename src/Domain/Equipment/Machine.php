<?php

namespace App\Domain\Equipment;

use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Geometry\Dimensions;

class Machine implements Interfaces\MachineInterface
{
    public function __construct(
        protected string $id,
        protected float $gripMarginSize,
        protected Dimensions $minSheetDimensions,
        protected Dimensions $maxSheetDimensions,
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
}