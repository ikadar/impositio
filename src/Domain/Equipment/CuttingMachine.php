<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\CuttingMachineInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Sheet\PrintFactory;

class CuttingMachine extends Machine implements CuttingMachineInterface
{

    protected float $setupDuration;
    protected float $paperCircuitDuration;
    protected float $cutDuration;
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

        $this->setSetupDuration($options["setup-duration"]);
        $this->setPaperCircuitDuration($options["paper-circuit-duration"]);
        $this->setCutDuration($options["cut-duration"]);
        $this->setCostPerHour($options["cost-per-hour"]);

    }

    public function getSetupDuration(): float
    {
        return $this->setupDuration;
    }

    public function setSetupDuration(float $setupDuration): CuttingMachine
    {
        $this->setupDuration = $setupDuration;
        return $this;
    }

    public function getPaperCircuitDuration(): float
    {
        return $this->paperCircuitDuration;
    }

    public function setPaperCircuitDuration(float $paperCircuitDuration): CuttingMachine
    {
        $this->paperCircuitDuration = $paperCircuitDuration;
        return $this;
    }

    public function getCutDuration(): float
    {
        return $this->cutDuration;
    }

    public function setCutDuration(float $cutDuration): CuttingMachine
    {
        $this->cutDuration = $cutDuration;
        return $this;
    }

    public function getCostPerHour(): float
    {
        return $this->costPerHour;
    }

    public function setCostPerHour(float $costPerHour): CuttingMachine
    {
        $this->costPerHour = $costPerHour;
        return $this;
    }

    public function setOpenPoseDimensions(Dimensions $dimensions): void
    {
        $this->setMinSheetDimensions($dimensions);
        $this->setMaxSheetDimensions($dimensions);
    }

    public function calculateCost(ActionPathNodeInterface $action): float
    {
        $duration = round($this->calculateSetupDuration($action) + $this->calculateRunDuration($action), 2);
        $cost =
            (
                $duration
                /
                60
            )
            *
            $this->getCostPerHour()
        ;

        return round($cost, 2);
    }

    public function calculateSetupDuration(ActionPathNodeInterface $action): float
    {
        return $this->getSetupDuration();
    }

    public function calculateRunDuration(ActionPathNodeInterface $action): float
    {
        $numberOfHandfuls =
            $action->getTodo()["numberOfCopies"]
            *
            (
                $action->getTodo()["paperWeight"]
                /
                115
            )
            /
            800
        ;
        $numberOfHandfuls = ceil($numberOfHandfuls);

        // calculate run duration
        $runDuration =
            $numberOfHandfuls
            *
            (
                (
                    $action->getTodo()["numberOfCuts"]
                    *
                    $this->getCutDuration()
                )
                +
                $this->getPaperCircuitDuration()
            )
        ;
        return round($runDuration, 2);

    }

}