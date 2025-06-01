<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\FolderInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Sheet\PrintFactory;

class StitchingMachine extends Machine implements FolderInterface
{
    protected float $baseSetupDuration;
    protected float $documentsPerHour;
    protected float $costPerHour;

    public function __construct(
        string $id,
        MachineType $type,
        float $gripMarginSize,
        Dimensions $minSheetDimensions,
        Dimensions $maxSheetDimensions,
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

        $options = $equipmentService->loadById($id);

        $this->setBaseSetupDuration($options["base-setup-duration"]);
        $this->setDocumentsPerHour($options["documents-per-hour"]);
        $this->setCostPerHour($options["cost-per-hour"]);

    }

    public function getBaseSetupDuration(): float
    {
        return $this->baseSetupDuration;
    }

    public function setBaseSetupDuration(float $baseSetupDuration): StitchingMachine
    {
        $this->baseSetupDuration = $baseSetupDuration;
        return $this;
    }

    public function getDocumentsPerHour(): float
    {
        return $this->documentsPerHour;
    }

    public function setDocumentsPerHour(float $documentsPerHour): StitchingMachine
    {
        $this->documentsPerHour = $documentsPerHour;
        return $this;
    }

    public function getCostPerHour(): float
    {
        return $this->costPerHour;
    }

    public function setCostPerHour(float $costPerHour): StitchingMachine
    {
        $this->costPerHour = $costPerHour;
        return $this;
    }



    public function calculateCost(ActionPathNodeInterface $action): float
    {
        $setupDuration = $this->calculateSetupDuration($action);
        $runDuration = $this->calculateSetupDuration($action);

        $duration = $setupDuration + $runDuration;
        $cost =
            (
                $duration
                /
                60
            )
            *
            $this->getCostPerHour()
        ;
        $cost = round($cost, 2);

        return $cost;
    }

    public function calculateSetupDuration(ActionPathNodeInterface $action): float
    {
        return $this->getBaseSetupDuration();
    }

    public function calculateRunDuration(ActionPathNodeInterface $action): float
    {
        return
            (
                $action->getTodo()["numberOfCopies"]
                /
                $this->getDocumentsPerHour()
            )
            *
            60
        ;
    }
    public function setOpenPoseDimensions(Dimensions $dimensions): void
    {
        $this->setMinSheetDimensions($dimensions);
        $this->setMaxSheetDimensions($dimensions);
    }

}