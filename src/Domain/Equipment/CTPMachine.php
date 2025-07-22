<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\CTPMachineInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Sheet\PrintFactory;

class CTPMachine extends Machine implements CTPMachineInterface
{
    protected float $sqmPerHour;
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

        $this->setsqmPerHour($options["sqm-per-hour"]);
        $this->setCostPerHour($options["cost-per-hour"]);

    }

    public function getSqmPerHour(): float
    {
        return $this->sqmPerHour;
    }

    public function setSqmPerHour(float $sqmPerHour): CTPMachine
    {
        $this->sqmPerHour = $sqmPerHour;
        return $this;
    }

    public function getCostPerHour(): float
    {
        return $this->costPerHour;
    }

    public function setCostPerHour(float $costPerHour): CTPMachine
    {
        $this->costPerHour = $costPerHour;
        return $this;
    }


    public function calculateCost(ActionPathNodeInterface $action): float | array
    {
        $pressSheetSqm = ($action->getPressSheet()->getWidth() * $action->getPressSheet()->getHeight()) / 1000000;

        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($action->getTodo()["inking"]));
        $inkCount = iterator_count($iterator);

        $duration = $this->calculateDuration($action);

        $plakettPrice = round(11.42 * $pressSheetSqm * $inkCount, 2); // € per sqm ; egész sheet méret * color per side

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

        return [
            "cost" => $cost,
            "aluSheetsCost" => $plakettPrice,
        ];
    }

    public function calculateDuration(ActionPathNodeInterface $action): float
    {

        $setupDuration = $this->calculateSetupDuration($action);
        $runDuration = $this->calculateRunDuration($action);
        return $setupDuration + $runDuration;
    }

    public function calculateSetupDuration(ActionPathNodeInterface $action): float
    {
        return 0;
    }

    public function calculateRunDuration(ActionPathNodeInterface $action): float
    {
        $sqm =
            (
                $action->getPressSheet()->getWidth()
                *
                $action->getPressSheet()->getHeight()
            )
            /
            1000000
        ;

        $duration = (
            (
                (
                    $sqm
                    *
                    1.05
                )
                /
                $this->getSqmPerHour()
            )
            *
            60
            *
            $action->getTodo()["numberOfColors"]
        )
        ;

        return round($duration, 2);
    }
}