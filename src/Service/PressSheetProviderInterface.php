<?php

namespace App\Service;

use App\Domain\Sheet\Interfaces\PressSheetInterface;

interface PressSheetProviderInterface
{
    /**
     * Get available press sheets for ActionTree processing.
     *
     * @param float $paperWeight Paper weight in g/m² (used for price calculation)
     * @return PressSheetInterface[]
     */
    public function getPressSheets(float $paperWeight = 120): array;
}
