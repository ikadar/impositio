<?php

namespace App\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\PartPayload;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

interface ActionParamsExtractorInterface
{
    /**
     * Extract ActionTree parameters from a PartPayload.
     *
     * @param PartPayload $part The part payload containing actions with params
     * @param PressSheetInterface[] $pressSheets Available press sheets
     * @return ActionTreeInput|null Returns null if no print action found or params invalid
     */
    public function extractForActionTree(PartPayload $part, array $pressSheets): ?ActionTreeInput;
}
