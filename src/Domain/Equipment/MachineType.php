<?php

namespace App\Domain\Equipment;

enum MachineType: string
{
    case PrintingPress = "printing press";
    case Folder = "folder";
    case StitchingMachine = "stitching machine";
    case CuttingMachine = "cutting machine";
    case CTPMachine = "ctp machine";
    case Sechage = "sechage";

    // New machine types for /process endpoint
    case CutoutMachine = "cutout machine";

    /**
     * @experimental Will be removed in future version
     */
    case Splitter = "splitter";

    /**
     * @experimental Will be removed in future version
     */
    case Assembler = "assembler";

    public function stopsImposition(): ?bool
    {
        return match ($this) {
            self::PrintingPress => false,
            self::Folder,
            self::StitchingMachine,
            self::CuttingMachine,
            self::CutoutMachine,
            self::Splitter,
            self::Assembler => true,
            default => null,
        };
    }

}
