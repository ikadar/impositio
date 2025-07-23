<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\CTPMachineInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\SechageInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Sheet\PrintFactory;

class Sechage extends Machine implements SechageInterface
{
    protected float $costPerHour;

    public function __construct(
        string $id,
        MachineType $type,
        string $designation,
        string $technicDesignation,
        int $capacity,
        int $expirationDateAlignment,
        int $nominalModeAttentionRequired,
        int $nominalModeProductivity,
        float $gripMarginSize,
        Dimensions $minSheetDimensions,
        Dimensions $maxSheetDimensions,
        protected ?int $maxPoseCount,
        PrintFactory $printFactory,
        EquipmentServiceInterface $equipmentService,
    )
    {
        parent::__construct(
            $id,
            $type,
            $designation,
            $technicDesignation,
            $capacity,
            $expirationDateAlignment,
            $nominalModeAttentionRequired,
            $nominalModeProductivity,
            $gripMarginSize,
            $minSheetDimensions,
            $maxSheetDimensions,
            $maxPoseCount,
            $printFactory,
            $equipmentService
        );

        $options = $equipmentService->loadById($id);

    }


    public function getCostPerHour(): float
    {
        return $this->costPerHour;
    }

    public function setCostPerHour(float $costPerHour): Sechage
    {
        $this->costPerHour = $costPerHour;
        return $this;
    }


    public function calculateCost(ActionPathNodeInterface $action): float | array
    {
        return 0;
    }

    public function calculateDuration(ActionPathNodeInterface $action): float
    {
        return $this->calculateSetupDuration($action) + $this->calculateRunDuration($action);
    }

    public function calculateSetupDuration(ActionPathNodeInterface $action): float
    {
        return 0;
    }

    public function calculateRunDuration(ActionPathNodeInterface $action): float
    {
        return 240;
    }
}