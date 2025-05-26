<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Sheet\PrintFactory;

class Machine implements MachineInterface
{
    public function __construct(
        protected string $id,
        protected MachineType $type,
        protected float $gripMarginSize,
        protected Dimensions $minSheetDimensions,
        protected Dimensions $maxSheetDimensions,
        protected PrintFactory $printFactory,
        protected EquipmentServiceInterface $equipmentService,
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

    public function setMinSheetDimensions(Dimensions $minSheetDimensions): Machine
    {
        $this->minSheetDimensions = $minSheetDimensions;
        return $this;
    }

    public function getMaxSheetDimensions(): Dimensions
    {
        return $this->maxSheetDimensions;
    }

    public function setMaxSheetDimensions(Dimensions $maxSheetDimensions): Machine
    {
        $this->maxSheetDimensions = $maxSheetDimensions;
        return $this;
    }


    public function getType(): MachineType
    {
        return $this->type;
    }

    public function setType(MachineType $type): Machine
    {
        $this->type = $type;
        return $this;
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


    public function setOpenPoseDimensions(Dimensions $dimensions): void
    {}

    public function calculateCost(ActionPathNodeInterface $action): float
    {
        return 999;
    }

}