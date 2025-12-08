<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\MachineType;

/**
 * AbstractAction implementation for the /process endpoint.
 *
 * Uses ActionName enum instead of the legacy ActionType enum.
 */
class ProcessAbstractAction implements AbstractActionInterface
{
    public function __construct(
        protected ActionName $actionName,
        protected EquipmentFactoryInterface $equipmentFactory,
    ) {}

    public function getActionName(): ActionName
    {
        return $this->actionName;
    }

    public function getMachineType(): MachineType
    {
        return $this->actionName->machineType();
    }

    public function getAvailableMachines(): array
    {
        // Cut action is handled automatically by ActionTree::extend() after print
        // So we return empty array to prevent duplicate processing
        if ($this->actionName === ActionName::Cut) {
            return [];
        }

        return $this->equipmentFactory->fromType($this->getMachineType());
    }
}
