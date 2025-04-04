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

        $gridFittings = $this->calculateExhaustiveGridFitting($data["press-sheet"], $data["zone"], $data["cutSpacing"]);

        return new JsonResponse(
            $gridFittings,
            JsonResponse::HTTP_OK
        );
    }

    public function calculateExhaustiveGridFitting(array $boundingArea, array $tileRect, $cutSpacing): array
    {
        $maxCols = floor(($boundingArea["width"]) / ($tileRect["width"] + (2 * $cutSpacing["horizontal"])));
        $maxRows = floor(($boundingArea["height"] - $boundingArea["grip-margin"]) / ($tileRect["height"] + (2 * $cutSpacing["vertical"])));

//        dump($maxCols, $maxRows);

        $gridFittings = [];
        for ($rowIndex = 1; $rowIndex <= $maxRows; $rowIndex++) {
            for ($colIndex = 1; $colIndex <= $maxCols; $colIndex++) {

                $gridFitting = [
                    "boundingArea" => $boundingArea,
                    "size" => sprintf("%dx%d", $colIndex, $rowIndex),
                    "placements" => $this->calculateGridFitting($colIndex, $rowIndex, $tileRect, $cutSpacing, $boundingArea["grip-margin"])
                ];

                $firstPlacement = $gridFitting["placements"][0];
                $lastPlacement = $gridFitting["placements"][array_key_last($gridFitting["placements"])];

                $gridFitting["usableArea"] = [
                    "x" => 0,
                    "y" => $boundingArea["grip-margin"],
                    "width" => $boundingArea["width"],
                    "height" => $boundingArea["height"] - $boundingArea["grip-margin"],
                ];


                $totalWidth = ($tileRect["width"] + (2 * $cutSpacing["vertical"])) * $colIndex;
                $unusedWidth = $boundingArea["width"] - $totalWidth;

                $totalHeight = ($tileRect["height"] + (2 * $cutSpacing["horizontal"])) * $rowIndex;
                $unusedHeight = $boundingArea["height"] - $boundingArea["grip-margin"] - $totalHeight;

                $gridFitting["usedArea"] = [
                    "x" => $unusedWidth / 2,
                    "y" => $boundingArea["grip-margin"] + ($unusedHeight / 2),
                    "width" => $lastPlacement["mmCutBufferPositions"]["width"] * $colIndex,
                    "height" => $lastPlacement["mmCutBufferPositions"]["height"] * $rowIndex,
                ];

                $gridFitting["firstTileWithCutBuffer"] = [
                    "x" => $firstPlacement["mmCutBufferPositions"]["x"],
                    "y" => $firstPlacement["mmCutBufferPositions"]["y"],
                    "width" => $firstPlacement["mmCutBufferPositions"]["width"],
                    "height" => $firstPlacement["mmCutBufferPositions"]["height"],
                ];

                $gridFitting["firstTile"] = [
                    "x" => $firstPlacement["mmPositions"]["x"],
                    "y" => $firstPlacement["mmPositions"]["y"],
                    "width" => $firstPlacement["mmPositions"]["width"],
                    "height" => $firstPlacement["mmPositions"]["height"],
                ];

                $gridFitting["trimLines"] = [
                    "top" => [
                        "x" => 0,
                        "y" => $gridFitting["usedArea"]["y"],
                        "length" => $gridFitting["boundingArea"]["width"],
                    ],
                    "bottom" => [
                        "x" => 0,
                        "y" => $gridFitting["usedArea"]["y"] + $gridFitting["usedArea"]["height"],
                        "length" => $gridFitting["boundingArea"]["width"],
                    ],
                    "left" => [
                        "x" => $gridFitting["usedArea"]["x"],
                        "y" => 0,
                        "length" => $gridFitting["boundingArea"]["height"],
                    ],
                    "right" => [
                        "x" => $gridFitting["usedArea"]["x"] + $gridFitting["usedArea"]["width"],
                        "y" => 0,
                        "length" => $gridFitting["boundingArea"]["height"],
                    ]
                ];

                $gridFittings[] = $gridFitting;
            }
        }

        return $gridFittings;
    }

    public function calculateGridFitting($cols, $rows, array $tileRect, array $cutSpacing, $gripMargin): array
    {

        $placements = [];
        for ($rowIndex = 0; $rowIndex < $rows; $rowIndex++) {
            for ($colIndex = 0; $colIndex < $cols; $colIndex++) {
                $placements[] = [
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

        return $placements;
    }
}