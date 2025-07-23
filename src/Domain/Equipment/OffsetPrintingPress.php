<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\OffsetPrintingPressInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Sheet\PrintFactory;

class OffsetPrintingPress extends PrintingPress implements OffsetPrintingPressInterface
{

    protected float $baseSetupDuration;
    protected float $setupDurationPerColor;
    protected bool $twoPass;
    protected int $passPerColor;
    protected int $sheetsPerHour;
    protected int $numberOfColors;
    protected float $maxInputStackHeight;
    protected float $stackReplenishmentDuration;

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

        $this->setBaseSetupDuration($options["base-setup-duration"]);
        $this->setSetupDurationPerColor($options["setup-duration-per-color"]);
        $this->setTwoPass($options["two-pass"]);
        $this->setPassPerColor($options["pass-per-color"]);
        $this->setSheetsPerHour($options["sheets-per-hour"]);
        $this->setMaxInputStackHeight($options["max-input-stack-height"]);
        $this->setStackReplenishmentDuration($options["stack-replenishment-duration"]);
        $this->setCostPerHour($options["cost-per-hour"]);
        $this->setNumberOfColors($options["colors"]);

    }

    public function getBaseSetupDuration(): float
    {
        return $this->baseSetupDuration;
    }

    public function setBaseSetupDuration(float $baseSetupDuration): OffsetPrintingPress
    {
        $this->baseSetupDuration = $baseSetupDuration;
        return $this;
    }

    public function getSetupDurationPerColor(): float
    {
        return $this->setupDurationPerColor;
    }

    public function setSetupDurationPerColor(float $setupDurationPerColor): OffsetPrintingPress
    {
        $this->setupDurationPerColor = $setupDurationPerColor;
        return $this;
    }

    public function isTwoPass(): bool
    {
        return $this->twoPass;
    }

    public function setTwoPass(bool $twoPass): OffsetPrintingPress
    {
        $this->twoPass = $twoPass;
        return $this;
    }

    public function getPassPerColor(): int
    {
        return $this->passPerColor;
    }

    public function setPassPerColor(int $passPerColor): OffsetPrintingPress
    {
        $this->passPerColor = $passPerColor;
        return $this;
    }

    public function getSheetsPerHour(): int
    {
        return $this->sheetsPerHour;
    }

    public function setSheetsPerHour(int $sheetsPerHour): OffsetPrintingPress
    {
        $this->sheetsPerHour = $sheetsPerHour;
        return $this;
    }

    public function getMaxInputStackHeight(): float
    {
        return $this->maxInputStackHeight;
    }

    public function setMaxInputStackHeight(float $maxInputStackHeight): OffsetPrintingPress
    {
        $this->maxInputStackHeight = $maxInputStackHeight;
        return $this;
    }

    public function getStackReplenishmentDuration(): float
    {
        return $this->stackReplenishmentDuration;
    }

    public function setStackReplenishmentDuration(float $stackReplenishmentDuration): OffsetPrintingPress
    {
        $this->stackReplenishmentDuration = $stackReplenishmentDuration;
        return $this;
    }

    public function getCostPerHour(): float
    {
        return $this->costPerHour;
    }

    public function setCostPerHour(float $costPerHour): OffsetPrintingPress
    {
        $this->costPerHour = $costPerHour;
        return $this;
    }

    public function getNumberOfColors(): int
    {
        return $this->numberOfColors;
    }

    public function setNumberOfColors(int $numberOfColors): OffsetPrintingPress
    {
        $this->numberOfColors = $numberOfColors;
        return $this;
    }


    public function calculateCost(ActionPathNodeInterface $action): float | array
    {
        // paper cost calculation
        $productsPerSheet = count($action->getGridFitting()->getTiles());
        $paperCostPerProduct = $action->getPressSheet()->getPrice() / $productsPerSheet;

        $paperCost = round($action->getTodo()["numberOfCopies"] * $paperCostPerProduct, 2);

        // setup duration calculation
        $setupDuration = $this->calculateSetupDuration($action);

        // Calculation of number of stack replenishments
        $numberOfPrintingSheets = ceil($action->getTodo()["numberOfCopies"] / $productsPerSheet);

        // Calculation of run duration
        $runDuration = $this->calculateRunDuration($action);

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

        return [
            "cost" => $cost,
            "paperCost" => $paperCost,
        ];
    }

    public function calculateSetupDuration(ActionPathNodeInterface $action): float
    {
        $setupDuration =
            $this->getBaseSetupDuration()
            +
            (
                $action->getTodo()["numberOfColors"]
                *
                $this->getSetupDurationPerColor()
            )
        ;
        return round($setupDuration, 2);
    }

    public function calculateRunDuration(ActionPathNodeInterface $action): float
    {
        // Calculation of number of stack replenishments
        $productsPerSheet = count($action->getGridFitting()->getTiles());
        $numberOfPrintingSheets = ceil($action->getTodo()["numberOfCopies"] / $productsPerSheet);
        $numberOfStackReplenishments =
            (
                $numberOfPrintingSheets
                *
                (
                    $action->getTodo()["paperWeight"]
                    /
                    115
                )
                /
                100
            )
            /
            $this->getMaxInputStackHeight()
        ;

        // Calculation of run duration
        $runDuration =
            (
                $numberOfStackReplenishments
                *
                $this->getStackReplenishmentDuration()
            )
            +
            (
                (
                    $numberOfPrintingSheets
                    /
                    $this->getSheetsPerHour()
                )
                *
                60
            )
        ;

        return round($runDuration, 2);
    }

}