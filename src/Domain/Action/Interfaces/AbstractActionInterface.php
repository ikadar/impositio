<?php

namespace App\Domain\Action\Interfaces;

use App\Domain\Equipment\MachineType;

interface AbstractActionInterface
{
    public function getMachineType(): MachineType;
    public function getAvailableMachines(): array;
}