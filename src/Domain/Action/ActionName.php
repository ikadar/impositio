<?php

namespace App\Domain\Action;

use App\Domain\Equipment\MachineType;

/**
 * Action names for the new /process endpoint.
 *
 * These replace the legacy ActionType enum values (printing, folding, stitching)
 * with the new explicit action names from the JSON payload.
 */
enum ActionName: string
{
    case Print = "print";
    case Cut = "cut";
    case Cutout = "cutout";

    /**
     * @experimental Will be removed in future version
     */
    case Split = "split";

    /**
     * @experimental Will be removed in future version
     */
    case Assembly = "assembly";

    public function machineType(): MachineType
    {
        return match ($this) {
            self::Print => MachineType::PrintingPress,
            self::Cut => MachineType::CuttingMachine,
            self::Cutout => MachineType::CutoutMachine,
            self::Split => MachineType::Splitter,
            self::Assembly => MachineType::Assembler,
        };
    }
}
