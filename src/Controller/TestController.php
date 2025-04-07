<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route(path: '/test', requirements: [], methods: ['POST'])]
    public function getTest(
    ): JsonResponse
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $boundingArea = $data["machine"]["input-dimensions"]["max"];
        $boundingArea["grip-margin"] = $data["machine"]["grip-margin"];

        $layouts = $this->calculateExhaustiveGridFitting($boundingArea, $data["zone"], $data["cutSpacing"]);

        $maxSheet = $data["machine"]["input-dimensions"]["max"];

        $minSheet = $data["machine"]["input-dimensions"]["min"];

        $gridFittings = [];
        foreach ($layouts as $gridFitting) {

            $gridFitting = $this->placeOnSheet($data["press-sheet"], $maxSheet, $minSheet, $data["machine"]["grip-margin"], $gridFitting);

            if ($this->layoutExceedsMaxSheet($gridFitting, $maxSheet)) {
                continue;
            }

            $gridFittings[] = $gridFitting;

        }

        return new JsonResponse(
            $gridFittings,
            JsonResponse::HTTP_OK
        );
    }

    public function layoutExceedsMaxSheet($gridFitting, $maxSheet)
    {
        return                 (
                ($gridFitting["cutSheet"]["gripMargin"]["position"] === "left")
                &&
                ($gridFitting["totalWidth"] + $gridFitting["cutSheet"]["gripMargin"]["width"] > ($maxSheet["width"]))
            )
            ||
            (
                ($gridFitting["cutSheet"]["gripMargin"]["position"] === "top")
                &&
                ($gridFitting["totalHeight"] + $gridFitting["cutSheet"]["gripMargin"]["height"]  > ($maxSheet["height"]))
            );
    }

    public function calculateExhaustiveGridFitting(array $boundingArea, array $tileRect, $cutSpacing): array
    {
        $maxCols = floor(($boundingArea["width"]) / ($tileRect["width"] + (2 * $cutSpacing["horizontal"])));
        $maxRows = floor(($boundingArea["height"]) / ($tileRect["height"] + (2 * $cutSpacing["vertical"])));
//        dump($maxCols, $maxRows);

        $gridFittings = [];
        for ($rowIndex = 1; $rowIndex <= $maxRows; $rowIndex++) {
            for ($colIndex = 1; $colIndex <= $maxCols; $colIndex++) {

                $tiles = $this->calculateGridFitting($colIndex, $rowIndex, $tileRect, $cutSpacing);

                $totalLayoutWidth = ($tileRect["width"] + (2 * $cutSpacing["vertical"])) * $colIndex;
                $totalLayoutHeight = ($tileRect["height"] + (2 * $cutSpacing["horizontal"])) * $rowIndex;


                $gridFitting = [
                    "tiles" => $tiles,
                    "size" => sprintf("%dx%d", $colIndex, $rowIndex),
                    "cols" => $colIndex,
                    "rows" => $rowIndex,
                    "totalWidth" => ($tileRect["width"] + (2 * $cutSpacing["vertical"])) * $colIndex,
                    "totalHeight" => ($tileRect["height"] + (2 * $cutSpacing["horizontal"])) * $rowIndex,
                ];

                $gridFittings[] = $gridFitting;
            }
        }

        return $gridFittings;
    }

    public function calculateGridFitting($cols, $rows, array $tileRect, array $cutSpacing): array
    {

        $tiles = [];
        for ($rowIndex = 0; $rowIndex < $rows; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $cols; $colIndex++) {
                $tiles[] = [
                    "mmPositions" => [
                        "x" =>
                            (($colIndex + 1) * $cutSpacing["horizontal"]) +
                            (($colIndex) * $cutSpacing["horizontal"]) +
                            $colIndex * ($tileRect["width"]),
                        "y" =>
                            (($rowIndex + 1) * $cutSpacing["vertical"]) +
                            (($rowIndex) * $cutSpacing["vertical"]) +
                            $rowIndex * ($tileRect["height"]),
                        "width" => $tileRect["width"],
                        "height" => $tileRect["height"],
                    ],
                    "mmCutBufferPositions" => [
                        "x" =>
                            ($colIndex * ($tileRect["width"] + (2 * $cutSpacing["horizontal"]))),
                        "y" =>
                            $rowIndex * ($tileRect["height"] + (2 * $cutSpacing["vertical"])),
                        "width" => $tileRect["width"] + (2 * $cutSpacing["horizontal"]),
                        "height" => $tileRect["height"] + (2 * $cutSpacing["vertical"]),
                    ],
                    "gridPosition" => [
                        "x" => $colIndex,
                        "y" => $rowIndex,
                    ]
                ];
            }
        }

        return $tiles;
    }

    public function placeOnSheet($pressSheet, $maxSheet, $minSheet, $gripMarginSize, $layout): array
    {

        $totalWidth = $layout["totalWidth"];
        $totalHeight = $layout["totalHeight"];
        $firstTile = $layout["tiles"][0];

        $layout["pressSheet"] = $pressSheet;

        $layout["maxSheet"] = $this->calculateMaxSheet($pressSheet, $maxSheet);
        $layout["minSheet"] = $this->calculateMinSheet($pressSheet, $minSheet);
        $layout["cutSheet"] = $this->calculateCutSheet($pressSheet, $gripMarginSize, $totalWidth, $totalHeight, $minSheet);

        $layout["layoutArea"] = $this->calculateLayoutArea($pressSheet, $layout["cutSheet"], $totalWidth, $totalHeight);

        $layout["firstTileWithCutBuffer"] = $this->calculateFirstTileWithCutBuffer($firstTile);
        $layout["firstTile"] = $this->calculateFirstTile($firstTile);
        $layout["trimLines"] = $this->calculateTrimLines($pressSheet, $layout["minSheet"], $layout["cutSheet"]);

        return $layout;
    }

    public function calculateFirstTile($firstTile)
    {
        return  [
            "x" => $firstTile["mmPositions"]["x"],
            "y" => $firstTile["mmPositions"]["y"],
            "width" => $firstTile["mmPositions"]["width"],
            "height" => $firstTile["mmPositions"]["height"],
        ];
    }

    public function calculateFirstTileWithCutBuffer($firstTile)
    {
        return  [
            "x" => $firstTile["mmCutBufferPositions"]["x"],
            "y" => $firstTile["mmCutBufferPositions"]["y"],
            "width" => $firstTile["mmCutBufferPositions"]["width"],
            "height" => $firstTile["mmCutBufferPositions"]["height"],
        ];
    }

    public function calculateMinSheet($pressSheet, $minSheet)
    {
        $minSheet["x"] = ($pressSheet["width"] - $minSheet["width"]) / 2;
        $minSheet["y"] = ($pressSheet["height"] - $minSheet["height"]) / 2;

        return $minSheet;
    }
    public function calculateMaxSheet($pressSheet, $maxSheet)
    {
        $maxSheet["x"] = ($pressSheet["width"] - $maxSheet["width"]) / 2;
        $maxSheet["y"] = ($pressSheet["height"] - $maxSheet["height"]) / 2;

        return $maxSheet;
    }

    public function calculateLayoutArea($pressSheet, $cutSheet, $totalWidth, $totalHeight)
    {
        $unusedWidth = $pressSheet["width"] - $totalWidth;
        $unusedHeight = $pressSheet["height"] - $totalHeight;

        $layoutArea = [
            "width" => $totalWidth,
            "height" => $totalHeight,
        ];

        $layoutArea["x"] = ($unusedWidth + ($cutSheet["gripMargin"]["position"] === "left" ? $cutSheet["gripMargin"]["size"] : 0)) / 2;
        $layoutArea["y"] = ($unusedHeight + ($cutSheet["gripMargin"]["position"] === "top" ? $cutSheet["gripMargin"]["size"] : 0)) / 2;

        return $layoutArea;
    }

    public function calculateCutSheet($pressSheet, $gripMarginSize, $totalLayoutWidth, $totalLayoutHeight, $minSheet)
    {
        $cutSheet = [
            "gripMargin" => [
                "size" => $gripMarginSize,
            ],
        ];

        $cutSheet["width"] = $totalLayoutWidth;
        $cutSheet["height"] = $totalLayoutHeight;

        $cutSheet["width"] = max($cutSheet["width"], $minSheet["width"]);
        $cutSheet["height"] = max($cutSheet["height"], $minSheet["height"]);

        $cutSheet["gripMargin"]["position"] = $cutSheet["width"] >= $cutSheet["height"] ? "top" : "left";

        if ($totalLayoutWidth > $minSheet["width"] && ($cutSheet["gripMargin"]["position"] === "left")) {
            $cutSheet["width"] += $cutSheet["gripMargin"]["size"];
        }

        if ($totalLayoutHeight > $minSheet["height"] && ($cutSheet["gripMargin"]["position"] === "top")) {
            $cutSheet["height"] += $cutSheet["gripMargin"]["size"];
        }

        $cutSheet["x"] = ($pressSheet["width"] - $cutSheet["width"]) / 2;
        $cutSheet["y"] = ($pressSheet["height"] - $cutSheet["height"]) / 2;

        $cutSheet["gripMargin"]["x"] = $cutSheet["x"];
        $cutSheet["gripMargin"]["y"] = $cutSheet["y"];
        $cutSheet["gripMargin"]["width"] = $cutSheet["gripMargin"]["position"] === "left" ? $cutSheet["gripMargin"]["size"] : $cutSheet["width"];
        $cutSheet["gripMargin"]["height"] = $cutSheet["gripMargin"]["position"] === "top" ? $cutSheet["gripMargin"]["size"] : $cutSheet["height"];

        return $cutSheet;

    }
    public function calculateTrimLines($pressSheet, $minSheet, $cutSheet )
    {
        return [
            "top" => [
                "x" => 0,
                "y" => min($cutSheet["y"], $minSheet["y"]),
                "length" => $pressSheet["width"],
            ],
            "bottom" => [
                "x" => 0,
                "y" => max($cutSheet["y"] + $cutSheet["height"], $minSheet["y"] + $minSheet["height"]),
                "length" => $pressSheet["width"],
            ],
            "left" => [
                "x" => min($cutSheet["x"], $minSheet["x"]),
                "y" => 0,
                "length" => $pressSheet["height"],
            ],
            "right" => [
                "x" => max($cutSheet["x"] + $cutSheet["width"], $minSheet["x"] + $minSheet["width"]),
                "y" => 0,
                "length" => $pressSheet["height"],
            ]
        ];
    }
}