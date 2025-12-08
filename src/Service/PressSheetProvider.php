<?php

namespace App\Service;

use App\Domain\Sheet\Interfaces\PressSheetInterface;
use App\Domain\Sheet\PrintFactory;

/**
 * Provides hardcoded press sheets for ActionTree processing.
 *
 * This can be extended later to load from database or config.
 */
class PressSheetProvider implements PressSheetProviderInterface
{
    public function __construct(
        private PrintFactory $printFactory,
    ) {}

    /**
     * Get available press sheets for ActionTree processing.
     *
     * @param float $paperWeight Paper weight in g/m² (used for price calculation)
     * @return PressSheetInterface[]
     */
    public function getPressSheets(float $paperWeight = 120): array
    {
        // Medium price per ton (hardcoded default)
        $mediumPricePerTon = 1150;

        return [
            $this->printFactory->newPressSheet(
                "pressSheet",
                0,
                0,
                1020,
                720,
                $this->calculateSheetPrice($mediumPricePerTon, $paperWeight, 720, 1020)
            ),
            $this->printFactory->newPressSheet(
                "pressSheet",
                0,
                0,
                880,
                640,
                $this->calculateSheetPrice($mediumPricePerTon, $paperWeight, 640, 880)
            ),
            $this->printFactory->newPressSheet(
                "pressSheet",
                0,
                0,
                740,
                540,
                $this->calculateSheetPrice($mediumPricePerTon, $paperWeight, 540, 740)
            ),
            $this->printFactory->newPressSheet(
                "pressSheet",
                0,
                0,
                520,
                360,
                $this->calculateSheetPrice($mediumPricePerTon, $paperWeight, 320, 450)
            ),
        ];
    }

    /**
     * Calculate sheet price based on dimensions and paper weight.
     */
    private function calculateSheetPrice(
        float $mediumPricePerTon,
        float $mediumWeightPerSqm,
        float $mediumHeight,
        float $mediumWidth
    ): float {
        $mediumPricePerGram = $mediumPricePerTon / (1000 * 1000);
        $mediumSqm = ($mediumHeight * $mediumWidth) / 1000000;
        $mediumWeight = $mediumWeightPerSqm * $mediumSqm;

        return $mediumWeight * $mediumPricePerGram;
    }
}
