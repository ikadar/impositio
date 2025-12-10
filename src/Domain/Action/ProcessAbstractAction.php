<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\MachineType;

/**
 * AbstractAction implementation for the /process endpoint.
 *
 * Uses ActionName enum instead of the legacy ActionType enum.
 * For print actions, contains PrintActionParams with inking info.
 */
class ProcessAbstractAction implements AbstractActionInterface
{
    public function __construct(
        protected ActionName $actionName,
        protected EquipmentFactoryInterface $equipmentFactory,
        protected ?PrintActionParams $printParams = null,
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

    /**
     * Get print-specific parameters if this is a print action.
     * Returns null for non-print actions (cut, fold, etc.)
     */
    public function getPrintParams(): ?PrintActionParams
    {
        return $this->printParams;
    }

    /**
     * Check if this is a print action.
     */
    public function isPrintAction(): bool
    {
        return $this->actionName === ActionName::Print;
    }
}
