<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\PrintingPressInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Sheet\PrintFactory;

class PrintingPress extends Machine implements PrintingPressInterface
{
//    protected float $baseSetupDuration;
//    protected float $setupDurationPerColor;
//    protected float $maxInputStackHeight;

    public function __construct(
        string $id,
        MachineType $type,
        float $gripMarginSize,
        Dimensions $minSheetDimensions,
        Dimensions $maxSheetDimensions,
//        $baseSetupDuration,
//        $setupDurationPerColor,
//        $maxInputStackHeight,
        PrintFactory $printFactory,
        EquipmentServiceInterface $equipmentService,
    )
    {
        parent::__construct(
            $id,
            $type,
            $gripMarginSize,
            $minSheetDimensions,
            $maxSheetDimensions,
            $printFactory,
            $equipmentService
        );

//        $this->setBaseSetupDuration($baseSetupDuration);
//        $this->setSetupDurationPerColor($setupDurationPerColor);
//        $this->setMaxInputStackHeight($maxInputStackHeight);

    }

//    public function getBaseSetupDuration(): float
//    {
//        return $this->baseSetupDuration;
//    }
//
//    public function setBaseSetupDuration(float $baseSetupDuration): PrintingPress
//    {
//        $this->baseSetupDuration = $baseSetupDuration;
//        return $this;
//    }
//
//    public function getSetupDurationPerColor(): float
//    {
//        return $this->setupDurationPerColor;
//    }
//
//    public function setSetupDurationPerColor(float $setupDurationPerColor): PrintingPress
//    {
//        $this->setupDurationPerColor = $setupDurationPerColor;
//        return $this;
//    }
//
//    public function getMaxInputStackHeight(): float
//    {
//        return $this->maxInputStackHeight;
//    }
//
//    public function setMaxInputStackHeight(float $maxInputStackHeight): PrintingPress
//    {
//        $this->maxInputStackHeight = $maxInputStackHeight;
//        return $this;
//    }


}