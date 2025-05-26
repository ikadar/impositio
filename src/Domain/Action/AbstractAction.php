<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\MachineType;

class AbstractAction implements AbstractActionInterface
{
    public function __construct(
        protected ActionType $actionType,
        protected EquipmentFactoryInterface $equipmentFactory,
    ) {
    }

    public function getActionType(): ActionType
    {
        return $this->actionType;
    }

    public function setActionType(ActionType $actionType): AbstractAction
    {
        $this->actionType = $actionType;
        return $this;
    }

    public function getMachineType(): MachineType
    {
        return MachineType::tryFrom($this->getActionType()->machineType()->value);
    }

    public function getAvailableMachines(): array
    {
        return $this->equipmentFactory->fromType($this->getActionType()->machineType());
    }
}