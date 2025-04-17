<?php

namespace App\Domain\Layout;

use App\Domain\Geometry\Interfaces\RectangleInterface;

class Packer implements Interfaces\PackerInterface
{

    public function calculateExhaustiveGridFitting(RectangleInterface $boundingArea, RectangleInterface $tileRect, CutSpacing $spacing, $rotated): array
    {
        $maxCols = floor(($boundingArea->getWidth()) / ($tileRect->getWidth() + (2 * $spacing->getHorizontalSpacing())));
        $maxRows = floor(($boundingArea->getHeight()) / ($tileRect->getHeight() + (2 * $spacing->getVerticalSpacing())));

        $gridFittings = [];
        for ($rowIndex = 1; $rowIndex <= $maxRows; $rowIndex++) {
            for ($colIndex = 1; $colIndex <= $maxCols; $colIndex++) {

                $tiles = $this->calculateGridFitting($colIndex, $rowIndex, $tileRect, $spacing);

                $gridFitting = [
                    "tiles" => $tiles,
                    "size" => sprintf("%dx%d", $colIndex, $rowIndex),
                    "rotated" => $rotated,
                    "cols" => $colIndex,
                    "rows" => $rowIndex,
                    "totalWidth" => ($tileRect->getWidth() + (2 * $spacing->getVerticalSpacing())) * $colIndex,
                    "totalHeight" => ($tileRect->getHeight() + (2 * $spacing->getHorizontalSpacing())) * $rowIndex,
                ];

                $gridFittings[] = $gridFitting;
            }
        }

        return $gridFittings;
    }

    public function calculateGridFitting($cols, $rows, RectangleInterface $tileRect, CutSpacing $cutSpacing): array
    {

        $tiles = [];
        for ($rowIndex = 0; $rowIndex < $rows; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $cols; $colIndex++) {
                $tiles[] = [
                    "mmPositions" => [
                        "x" =>
                            (($colIndex + 1) * $cutSpacing->getHorizontalSpacing()) +
                            (($colIndex) * $cutSpacing->getHorizontalSpacing()) +
                            $colIndex * ($tileRect->getWidth()),
                        "y" =>
                            (($rowIndex + 1) * $cutSpacing->getVerticalSpacing()) +
                            (($rowIndex) * $cutSpacing->getVerticalSpacing()) +
                            $rowIndex * ($tileRect->getHeight()),
                        "width" => $tileRect->getWidth(),
                        "height" => $tileRect->getHeight(),
                    ],
                    "mmCutBufferPositions" => [
                        "x" =>
                            ($colIndex * ($tileRect->getWidth() + (2 * $cutSpacing->getHorizontalSpacing()))),
                        "y" =>
                            $rowIndex * ($tileRect->getHeight() + (2 * $cutSpacing->getVerticalSpacing())),
                        "width" => $tileRect->getWidth() + (2 * $cutSpacing->getHorizontalSpacing()),
                        "height" => $tileRect->getHeight() + (2 * $cutSpacing->getVerticalSpacing()),
                    ],
                    "gridPosition" => [
                        "x" => $colIndex,
                        "y" => $rowIndex,
                    ]
                ];
            }
        }

        return $tiles;
    }}