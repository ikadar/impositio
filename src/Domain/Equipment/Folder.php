<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\FolderInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Sheet\PrintFactory;

class Folder extends Machine implements FolderInterface
{
    protected float $numberOfFolds;
    protected float $documentSpacing;
    protected float $metersPerHour;
    protected float $baseSetupDuration;
    protected float $setupDurationByFold;
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

        $this->setNumberOfFolds($options["number-of-folds"][0]);
        $this->setDocumentSpacing($options["document-spacing"]);
        $this->setMetersPerHour($options["meters-per-hour"]);
        $this->setBaseSetupDuration($options["base-setup-duration"]);
        $this->setSetupDurationByFold($options["setup-duration-by-fold"]);
        $this->setCostPerHour($options["cost-per-hour"]);

    }

    public function getNumberOfFolds(): float
    {
        return $this->numberOfFolds;
    }

    public function setNumberOfFolds(float $numberOfFolds): Folder
    {
        $this->numberOfFolds = $numberOfFolds;
        return $this;
    }

    public function getDocumentSpacing(): float
    {
        return $this->documentSpacing;
    }

    public function setDocumentSpacing(float $documentSpacing): Folder
    {
        $this->documentSpacing = $documentSpacing;
        return $this;
    }

    public function getMetersPerHour(): float
    {
        return $this->metersPerHour;
    }

    public function setMetersPerHour(float $metersPerHour): Folder
    {
        $this->metersPerHour = $metersPerHour;
        return $this;
    }

    public function getBaseSetupDuration(): float
    {
        return $this->baseSetupDuration;
    }

    public function setBaseSetupDuration(float $baseSetupDuration): Folder
    {
        $this->baseSetupDuration = $baseSetupDuration;
        return $this;
    }

    public function getSetupDurationByFold(): float
    {
        return $this->setupDurationByFold;
    }

    public function setSetupDurationByFold(float $setupDurationByFold): Folder
    {
        $this->setupDurationByFold = $setupDurationByFold;
        return $this;
    }

    public function getCostPerHour(): float
    {
        return $this->costPerHour;
    }

    public function setCostPerHour(float $costPerHour): Folder
    {
        $this->costPerHour = $costPerHour;
        return $this;
    }

    public function calculateCost(ActionPathNodeInterface $action): float
    {
        $setupDuration = $this->calculateSetupDuration($action);
        $runDuration = $this->calculateRunDuration($action);

        $duration = $setupDuration + $runDuration;
        $cost = ($duration / 60) * $this->getCostPerHour();

//        $actionData["numberOfSheets"] = $action->getTodo()["numberOfCopies"];
//        $actionData["setupDuration"] = round($setupDuration, 2);
//        $actionData["runDuration"] = round($runDuration, 2);
//        $actionData["cost"] = round($foldingCost, 2);
//
//        $totalDuration += $duration;
//        $totalCost += $cost;


        return $cost;
    }

    public function calculateSetupDuration(ActionTreeNodeInterface $action): float
    {
        return $this->getBaseSetupDuration()
            +
            (
                $this->getNumberOfFolds()      // ez a valódi hajtások száma
                *
                $this->getSetupDurationByFold()
            )
        ;
    }

    public function calculateRunDuration(ActionPathNodeInterface $action): float
    {
        return
            (
                (
                    (
                        $action->getTodo()["inputSheetLength"]
                        +
                        $this->getDocumentSpacing()
                    )
                    *
                    $action->getTodo()["numberOfCopies"]
                )
                /
                $this->getMetersPerHour()
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