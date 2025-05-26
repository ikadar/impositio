<?php

namespace App\Domain\Action;

use App\Domain\Equipment\MachineType;

enum ActionType: string
{
    case Printing = "printing";
    case Folding = "folding";
    case Stitching = "stitching";

    public function machineType(): ?MachineType
    {
        return match ($this) {
            self::Printing => MachineType::PrintingPress,
            self::Folding => MachineType::Folder,
            self::Stitching => MachineType::StitchingMachine,
        };
    }

}
