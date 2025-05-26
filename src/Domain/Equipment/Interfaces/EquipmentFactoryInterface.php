<?php

namespace App\Domain\Equipment\Interfaces;

interface EquipmentFactoryInterface
{
    public function fromId(string $id): MachineInterface;
}