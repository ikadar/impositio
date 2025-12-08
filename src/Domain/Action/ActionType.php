<?php

namespace App\Domain\Action;

use App\Domain\Equipment\MachineType;

/**
 * @deprecated Use ActionName enum instead for the new /process endpoint.
 * @see \App\Domain\Action\ActionName
 */
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
