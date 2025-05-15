<?php

namespace App\Domain\Equipment;

use App\Domain\Equipment\Interfaces\PrintingPressInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Sheet\PrintFactory;

class PrintingPress extends Machine implements PrintingPressInterface
{
    public function __construct(
        string $id,
        MachineType $type,
        float $gripMarginSize,
        Dimensions $minSheetDimensions,
        Dimensions $maxSheetDimensions,
        PrintFactory $printFactory
    )
    {
        parent::__construct($id, $type, $gripMarginSize, $minSheetDimensions, $maxSheetDimensions, $printFactory);
    }
}