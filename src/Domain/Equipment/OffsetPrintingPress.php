<?php

namespace App\Domain\Equipment;

use App\Domain\Equipment\Interfaces\OffsetPrintingPressInterface;
use App\Domain\Equipment\PrintingPress;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Sheet\PrintFactory;

class OffsetPrintingPress extends PrintingPress implements Interfaces\OffsetPrintingPressInterface
{

    protected float $baseSetupDuration;
    protected float $setupDurationPerColor;
    protected bool $twoPass;
    protected int $passPerColor;
    protected int $sheetsPerHour;
    protected float $maxInputStackHeight;
    protected float $stackReplenishmentDuration;

    public function __construct(
        string $id,
        MachineType $type,
        float $gripMarginSize,
        Dimensions $minSheetDimensions,
        Dimensions $maxSheetDimensions,
        PrintFactory $printFactory,
        array $options = []
    )
    {
        parent::__construct($id, $type, $gripMarginSize, $minSheetDimensions, $maxSheetDimensions, $printFactory);

        $defaultOptions = [
            "base-setup-duration" => 6.6666,
            "setup-duration-per-color" => 3.3333,
            "two-pass" => false,
            "pass-per-color" => 75,
            "sheets-per-hour" => 8000,
            "max-input-stack-height" => 120,
            "stack-replenishment-duration" => 3
        ];

        $options = array_merge($defaultOptions, $options);

        $this->setBaseSetupDuration($options["base-setup-duration"]);
        $this->setSetupDurationPerColor($options["setup-duration-per-color"]);
        $this->setTwoPass($options["two-pass"]);
        $this->setPassPerColor($options["pass-per-color"]);
        $this->setSheetsPerHour($options["sheets-per-hour"]);
        $this->setMaxInputStackHeight($options["max-input-stack-height"]);
        $this->setStackReplenishmentDuration($options["stack-replenishment-duration"]);

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



}