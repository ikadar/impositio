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
        $boundingArea["gripMargin"] = $data["machine"]["gripMargin"];

        $maxSheet = $data["machine"]["input-dimensions"]["max"];
        $minSheet = $data["machine"]["input-dimensions"]["min"];

        $zone = $data["zone"];
        $zoneWithoutGripMargin = $data["zone"];
        $zoneWithoutGripMargin["width"] = $zoneWithoutGripMargin["width"] - ($zoneWithoutGripMargin["gripMargin"]["position"] === "left" ? $zoneWithoutGripMargin["gripMargin"]["size"] : 0);
        $zoneWithoutGripMargin["height"] = $zoneWithoutGripMargin["height"] - ($zoneWithoutGripMargin["gripMargin"]["position"] === "top" ? $zoneWithoutGripMargin["gripMargin"]["size"] : 0);

        $gridFittings = [];

        // --- UNROTATED
        $exhaustiveGridFittings = $this->calculateExhaustiveGridFitting($boundingArea, $zone, $data["cutSpacing"], false);
        $zoneGripMargin = $zone["gripMargin"];

        foreach ($exhaustiveGridFittings as $placedGridFitting) {

            $placedGridFitting = $this->placeOnSheet($data["press-sheet"], $maxSheet, $minSheet, $data["machine"]["gripMargin"], $placedGridFitting, $zoneGripMargin);

            if ($this->layoutExceedsMaxSheet($placedGridFitting, $maxSheet)) {
                continue;
            }

            $gridFittings[] = $placedGridFitting;
        }

        // --- ROTATED

        $zoneGripMargin = $zone["gripMargin"];
        $zoneGripMargin["position"] = $zoneGripMargin["position"] === "top" ? "left" : "top";

        $rotatedZone = ["width" => $zone["height"], "height" => $zone["width"]];
        $exhaustiveGridFittings = $this->calculateExhaustiveGridFitting($boundingArea, $rotatedZone, $data["cutSpacing"], true);

        foreach ($exhaustiveGridFittings as $placedGridFitting) {

            $placedGridFitting = $this->placeOnSheet($data["press-sheet"], $maxSheet, $minSheet, $data["machine"]["gripMargin"], $placedGridFitting, $zoneGripMargin);

            if ($this->layoutExceedsMaxSheet($placedGridFitting, $maxSheet)) {
                continue;
            }

            $gridFittings[] = $placedGridFitting;
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

    public function calculateExhaustiveGridFitting(array $boundingArea, array $tileRect, $cutSpacing, $rotated): array
    {
        $maxCols = floor(($boundingArea["width"]) / ($tileRect["width"] + (2 * $cutSpacing["horizontal"])));
        $maxRows = floor(($boundingArea["height"]) / ($tileRect["height"] + (2 * $cutSpacing["vertical"])));

        $gridFittings = [];
        for ($rowIndex = 1; $rowIndex <= $maxRows; $rowIndex++) {
            for ($colIndex = 1; $colIndex <= $maxCols; $colIndex++) {

                $tiles = $this->calculateGridFitting($colIndex, $rowIndex, $tileRect, $cutSpacing);

                $gridFitting = [
                    "tiles" => $tiles,
                    "size" => sprintf("%dx%d", $colIndex, $rowIndex),
                    "rotated" => $rotated,
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

    public function placeOnSheet($pressSheet, $maxSheet, $minSheet, $gripMarginSize, $layout, $zoneGripMargin): array
    {

        $totalWidth = $layout["totalWidth"];
        $totalHeight = $layout["totalHeight"];
        $firstTile = $layout["tiles"][0];

        $layout["pressSheet"] = $pressSheet;

        $layout["maxSheet"] = $this->calculateMaxSheet($pressSheet, $maxSheet);
        $layout["minSheet"] = $this->calculateMinSheet($pressSheet, $minSheet);

        $layout["cutSheet"] = $this->calculateCutSheet($pressSheet, $gripMarginSize, $totalWidth, $totalHeight, $minSheet, $zoneGripMargin);
        $layout["layoutArea"] = $this->calculateLayoutArea($layout, $zoneGripMargin);
//        $layout["layoutArea"] = $this->calculateLayoutArea($pressSheet, $layout["cutSheet"], $totalWidth, $totalHeight);

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

    public function calculateLayoutArea($layout, $zoneGripMargin)
    {
        // take the content's dimensions including inner (content's) grip margins
        $layoutWithInnerGripMargins = [
            "width" => $layout["totalWidth"],
            "height" => $layout["totalHeight"],
        ];

        $gripMarginOverlap = max(0, ($zoneGripMargin["size"] - $layout["cutSheet"]["gripMargin"]["size"]));

        // also take the content's dimensions excluding inner (content's) grip margins
        $layoutWithoutInnerGripMargins = [
            "width" => $layoutWithInnerGripMargins["width"] - $gripMarginOverlap,
            "height" => $layoutWithInnerGripMargins["height"] - $gripMarginOverlap,
        ];

        $bothOnLeft = ($zoneGripMargin["position"] === "left" && $layout["cutSheet"]["gripMargin"]["position"] === "left");
        $bothOnTop = ($zoneGripMargin["position"] === "top" && $layout["cutSheet"]["gripMargin"]["position"] === "top");

        // center the layout on the usable area of the cut sheet
        $layoutArea = $this->centerOn($layout["cutSheet"]["usableArea"], [
            "width" => $bothOnLeft ? $layoutWithoutInnerGripMargins["width"] : $layoutWithInnerGripMargins["width"],
            "height" => $bothOnTop ? $layoutWithoutInnerGripMargins["height"] : $layoutWithInnerGripMargins["height"],
        ]);

        if ($bothOnLeft) {
            $layoutArea["x"] -= $gripMarginOverlap;
        }

        if ($bothOnTop) {
            $layoutArea["y"] -= $gripMarginOverlap;
        }

        return $layoutArea;
    }

    public function calculateCutSheet($pressSheet, $gripMarginSize, $totalLayoutWidth, $totalLayoutHeight, $minSheet, $zoneGripMargin)
    {
        $cutSheet = [
            "gripMargin" => [
                "size" => $gripMarginSize,
            ],
        ];

        // precalculate $cutSheetGripMarginPosition
        $cutSheetGripMarginPosition = max($totalLayoutWidth, $minSheet["width"]) >= max($totalLayoutHeight, $minSheet["height"]) ? "top" : "left";

        $bothOnTop = ($zoneGripMargin["position"] === "top" && $cutSheetGripMarginPosition === "top");
        $bothOnLeft = ($zoneGripMargin["position"] === "left" && $cutSheetGripMarginPosition === "left");

        if ($cutSheetGripMarginPosition === "left" && !$bothOnLeft) {
            $totalLayoutWidth += $zoneGripMargin["size"];
        }

        if ($cutSheetGripMarginPosition === "top" && !$bothOnTop) {
            $totalLayoutHeight += $zoneGripMargin["size"];
        }

        // calculate cut sheet size
        $cutSheet["width"] = $totalLayoutWidth;
        $cutSheet["height"] = $totalLayoutHeight;

        $cutSheet["width"] = max($cutSheet["width"], $minSheet["width"]);
        $cutSheet["height"] = max($cutSheet["height"], $minSheet["height"]);

        // center cut sheet on the press sheet
        $cutSheet = $this->center($pressSheet, $cutSheet);

        // determine grip margin position
        $cutSheet["gripMargin"]["position"] = $cutSheet["width"] >= $cutSheet["height"] ? "top" : "left";

        if (!$bothOnLeft && $cutSheet["gripMargin"]["position"] === "left" && ($cutSheet["width"] > $minSheet["width"])) {
            $cutSheet["width"] += $cutSheet["gripMargin"]["size"];
        }

        if (!$bothOnTop && $cutSheet["gripMargin"]["position"] === "top" && ($cutSheet["height"] > $minSheet["height"])) {
            $cutSheet["height"] += $cutSheet["gripMargin"]["size"];
        }


        // calculate grip margin position and dimensions
        $cutSheet["gripMargin"]["x"] = $cutSheet["x"];
        $cutSheet["gripMargin"]["y"] = $cutSheet["y"];
        $cutSheet["gripMargin"]["width"] = $cutSheet["gripMargin"]["position"] === "left" ? $cutSheet["gripMargin"]["size"] : $cutSheet["width"];
        $cutSheet["gripMargin"]["height"] = $cutSheet["gripMargin"]["position"] === "top" ? $cutSheet["gripMargin"]["size"] : $cutSheet["height"];





        // calculate cutSheet's usable area - START
        $cutSheet["usableArea"] = [
            "x" => $cutSheet["x"],
            "y" => $cutSheet["y"],
            "width" => $cutSheet["width"],
            "height" => $cutSheet["height"],
        ];

        // calculate sizes without the grip margin
        if ($cutSheet["gripMargin"]["position"] === "left") {
            $cutSheet["usableArea"]["x"] += $cutSheet["gripMargin"]["size"];
            $cutSheet["usableArea"]["width"] -= $cutSheet["gripMargin"]["size"];
        }
        if ($cutSheet["gripMargin"]["position"] === "top") {
            $cutSheet["usableArea"]["y"] += $cutSheet["gripMargin"]["size"];
            $cutSheet["usableArea"]["height"] -= $cutSheet["gripMargin"]["size"];
        }
        // calculate cutSheet's usable area - END

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

    public function center($container, $area)
    {
        $unusedWidth = $container["width"] - $area["width"];
        $unusedHeight = $container["height"] - $area["height"];

        $area["x"] = $unusedWidth / 2;
        $area["y"] = $unusedHeight / 2;

        return $area;
    }

    public function placeOnto($container, $area)
    {
        $area["x"] = $container["x"] + $area["x"];
        $area["y"] = $container["y"] + $area["y"];

        return $area;
    }

    public function centerOn($container, $area)
    {
        $area = $this->center($container, $area);
        $area = $this->placeOnto($container, $area);

        return $area;
    }
}