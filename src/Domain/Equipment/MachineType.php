<?php

namespace App\Domain\Equipment;

enum MachineType: string
{
    case PrintingPress = "printing press";
    case Folder = "folder";
    case StitchingMachine = "stitching machine";

    public function stopsImposition(): ?float
    {
        return match ($this) {
            self::PrintingPress => false,
            self::Folder, self::StitchingMachine => true,
        };
    }

}
