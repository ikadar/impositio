<?php

namespace App\Domain\Equipment;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
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
        protected string $designation,
        protected string $technicDesignation,
        protected int $capacity,
        protected int $expirationDateAlignment,
        protected int $nominalModeAttentionRequired,
        protected int $nominalModeProductivity,
        protected float $gripMarginSize,
        protected Dimensions $minSheetDimensions,
        protected Dimensions $maxSheetDimensions,
        protected ?int $maxPoseCount,
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

    public function getDesignation(): string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): Machine
    {
        $this->designation = $designation;
        return $this;
    }

    public function getTechnicDesignation(): string
    {
        return $this->technicDesignation;
    }

    public function setTechnicDesignation(string $technicDesignation): Machine
    {
        $this->technicDesignation = $technicDesignation;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): Machine
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getExpirationDateAlignment(): int
    {
        return $this->expirationDateAlignment;
    }

    public function setExpirationDateAlignment(int $expirationDateAlignment): Machine
    {
        $this->expirationDateAlignment = $expirationDateAlignment;
        return $this;
    }

    public function getNominalModeAttentionRequired(): int
    {
        return $this->nominalModeAttentionRequired;
    }

    public function setNominalModeAttentionRequired(int $nominalModeAttentionRequired): Machine
    {
        $this->nominalModeAttentionRequired = $nominalModeAttentionRequired;
        return $this;
    }

    public function getNominalModeProductivity(): int
    {
        return $this->nominalModeProductivity;
    }

    public function setNominalModeProductivity(int $nominalModeProductivity): Machine
    {
        $this->nominalModeProductivity = $nominalModeProductivity;
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

    public function getOrdoData(): array
    {
        return [
            "id" => $this->getId(),
            "designation" => $this->getDesignation(),
            "designation_technique" => $this->getTechnicDesignation(),
            "type" => $this->getType()->name,
            "capacite" => $this->getCapacity(),
            "peremption_calage" => $this->getExpirationDateAlignment(),
            "regime_nominal" => [
                "attention_requise" => $this->getNominalModeAttentionRequired(),
                "productivite" => $this->getNominalModeProductivity()
            ]
        ];
    }

    public function getMaxPoseCount(): ?int
    {
        return $this->maxPoseCount;
    }

    public function setMaxPoseCount(int $maxPoseCount): Machine
    {
        $this->maxPoseCount = $maxPoseCount;
        return $this;
    }

}