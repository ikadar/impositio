<?php

namespace App\Domain\Layout;

use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\Machine;
use App\Domain\Geometry\AlignmentMode;
use App\Domain\Geometry\Interfaces\PositionedRectangleInterface;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Geometry\PositionedRectangle;
use App\Domain\Layout\Interfaces\PackerInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\PrintFactory;

class Calculator
{

    public function __construct(
        protected PackerInterface $packer,
        protected PrintFactory $printFactory,
    )
    {
    }

    public function calculateGridFittings(MachineInterface $machine, RectangleInterface $boundingArea, InputSheetInterface $zone, CutSpacing $cutSpacing, RectangleInterface $pressSheet, RectangleInterface $maxSheet, RectangleInterface $minSheet): array
    {
        $rotatedZone = $this->printFactory->newInputSheet(
            "rotatedZone",
            0,
            0,
            $zone->getHeight(),
            $zone->getWidth()
        );
        $rotatedZone->setGripMarginSize($zone->getGripMarginSize());

        return array_merge(
            $this->calculateUnRotatedGridFittings($machine, $boundingArea, $zone, $pressSheet, $cutSpacing, $maxSheet, $minSheet, false),
            $this->calculateUnRotatedGridFittings($machine, $boundingArea, $rotatedZone, $pressSheet, $cutSpacing, $maxSheet, $minSheet, true)
        );
    }

    protected function calculateUnRotatedGridFittings(Machine $machine, RectangleInterface $boundingArea, InputSheetInterface $zone, RectangleInterface $pressSheet, $spacing, RectangleInterface $maxSheet, RectangleInterface $minSheet, $rotated): array
    {
        $gridFittings = [];

        $exhaustiveGridFittings = $this->packer->calculateExhaustiveGridFitting($boundingArea, $zone, $spacing, $rotated);

        $zoneGripMargin = [
            "size" => $zone->getGripMarginSize(),
            "position" => strtolower($zone->getGripMarginPosition()->name),
            "x" => $zone->getLeft(),
            "y" => $zone->getTop(),
            "width" => $zone->getWidth(),
            "height" => $zone->getHeight(),
        ];

        foreach ($exhaustiveGridFittings as $placedGridFitting) {

            $machineGripMargin = null; // TODO

            // todo:
            $placedGridFitting["maxSheet"] = json_decode($maxSheet->toJson());
            $placedGridFitting["minSheet"] = json_decode($minSheet->toJson());

            $placedGridFitting = $this->placeOnSheet($pressSheet, $minSheet, $machine->getGripMarginSize(), $placedGridFitting, $zoneGripMargin);

            if ($this->layoutExceedsMaxSheet($placedGridFitting, $maxSheet)) {
                continue;
            }

            $gridFittings[] = $placedGridFitting;
        }

        return $gridFittings;
    }


    public function calculateTrimLines(RectangleInterface $pressSheet, RectangleInterface $minSheet, $cutSheet )
    {
        return [
            "top" => [
                "x" => 0,
                "y" => min($cutSheet["y"], $minSheet->getTop()),
                "length" => $pressSheet->getWidth(),
            ],
            "bottom" => [
                "x" => 0,
                "y" => max($cutSheet["y"] + $cutSheet["height"], $minSheet->getTop() + $minSheet->getHeight()),
                "length" => $pressSheet->getWidth(),
            ],
            "left" => [
                "x" => min($cutSheet["x"], $minSheet->getLeft()),
                "y" => 0,
                "length" => $pressSheet->getHeight(),
            ],
            "right" => [
                "x" => max($cutSheet["x"] + $cutSheet["width"], $minSheet->getLeft() + $minSheet->getWidth()),
                "y" => 0,
                "length" => $pressSheet->getHeight(),
            ]
        ];
    }

    public function placeOnSheet(RectangleInterface $pressSheet, RectangleInterface $minSheet, $gripMarginSize, $layout, $zoneGripMargin): array
    {

        $totalWidth = $layout["totalWidth"];
        $totalHeight = $layout["totalHeight"];
        $firstTile = $layout["tiles"][0];

        // Todo: move
        $layout["pressSheet"] = json_decode($pressSheet->toJson());;

        $cutSheet = $this->calculateCutSheet($pressSheet, $gripMarginSize, $totalWidth, $totalHeight, $minSheet, $zoneGripMargin);
        // Todo: move
        $layout["cutSheet"] = [
            "gripMargin" => [
                "size" => $cutSheet->getGripMarginSize(),
                "position" => strtolower($cutSheet->getGripMarginPosition()->name),
                "x" => $cutSheet->getChildById("gripMargin")->getAbsoluteLeft(),
                "y" => $cutSheet->getChildById("gripMargin")->getAbsoluteTop(),
                "width" => $cutSheet->getChildById("gripMargin")->getWidth(),
                "height" => $cutSheet->getChildById("gripMargin")->getHeight(),
            ],
            "usableArea" => [
                "x" => $cutSheet->getChildById("usableArea")->getAbsoluteLeft(),
                "y" => $cutSheet->getChildById("usableArea")->getAbsoluteTop(),
                "width" => $cutSheet->getChildById("usableArea")->getWidth(),
                "height" => $cutSheet->getChildById("usableArea")->getHeight(),
            ],
            "x" => $cutSheet->getAbsoluteLeft(),
            "y" => $cutSheet->getAbsoluteTop(),
            "width" => $cutSheet->getWidth(),
            "height" => $cutSheet->getHeight(),

        ];

        $layoutArea = $this->calculateLayoutArea($totalWidth, $totalHeight, $cutSheet, $zoneGripMargin);

        // Todo: move
        $layout["layoutArea"] = [
            "x" => $layoutArea->getAbsoluteLeft(),
            "y" => $layoutArea->getAbsoluteTop(),
            "width" => $layoutArea->getWidth(),
            "height" => $layoutArea->getHeight(),
        ];

        $layout["firstTileWithCutBuffer"] = $this->calculateFirstTileWithCutBuffer($firstTile);
        $layout["firstTile"] = $this->calculateFirstTile($firstTile);

        $layout["trimLines"] = $this->calculateTrimLines($pressSheet, $minSheet, $layout["cutSheet"]);

        return $layout;
    }

    public function calculateLayoutArea($totalWidth, $totalHeight, InputSheetInterface $cutSheet, $zoneGripMargin): RectangleInterface
    {
        // take the content's dimensions including inner (content's) grip margins
        $layoutWithInnerGripMargins = [
            "width" => $totalWidth,
            "height" => $totalHeight,
        ];

        $gripMarginOverlap = max(0, ($zoneGripMargin["size"] - $cutSheet->getGripMarginSize()));

        // also take the content's dimensions excluding inner (content's) grip margins
        $layoutWithoutInnerGripMargins = [
            "width" => $layoutWithInnerGripMargins["width"] - $gripMarginOverlap,
            "height" => $layoutWithInnerGripMargins["height"] - $gripMarginOverlap,
        ];


        $bothOnLeft = ($zoneGripMargin["position"] === "left" && strtolower($cutSheet->getGripMarginPosition()->name) === "left");
        $bothOnTop = ($zoneGripMargin["position"] === "top" && strtolower($cutSheet->getGripMarginPosition()->name) === "top");

        // center the layout on the usable area of the cut sheet

        $layoutArea = $this->printFactory->newRectangle(
            "layoutArea",
            0,
            0,
            $bothOnLeft ? $layoutWithoutInnerGripMargins["width"] : $layoutWithInnerGripMargins["width"],
            $bothOnTop ? $layoutWithoutInnerGripMargins["height"] : $layoutWithInnerGripMargins["height"],
        );
        $layoutArea->alignTo($cutSheet->getChildById("usableArea"), AlignmentMode::MiddleCenterToMiddleCenter);


//        if ($bothOnLeft) {
//            $layoutArea["x"] -= $gripMarginOverlap;
//        }
//
//        if ($bothOnTop) {
//            $layoutArea["y"] -= $gripMarginOverlap;
//        }

        return $layoutArea;
    }

    public function calculateCutSheet(RectangleInterface $pressSheet, $gripMarginSize, $totalLayoutWidth, $totalLayoutHeight, RectangleInterface $minSheet, $zoneGripMargin): InputSheetInterface
    {
        $cutSheet = $this->printFactory->newInputSheet(
            "cutSheet",
            0,
            0,
            max($totalLayoutWidth, $minSheet->getWidth()),
            max($totalLayoutHeight, $minSheet->getHeight()),
        );
        $cutSheet->setGripMarginSize($gripMarginSize);
        $cutSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        return $cutSheet;

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

    public function layoutExceedsMaxSheet($gridFitting, RectangleInterface $maxSheet): bool
    {
        return
            (
                ($gridFitting["cutSheet"]["gripMargin"]["position"] === "left")
                &&
                ($gridFitting["totalWidth"] + $gridFitting["cutSheet"]["gripMargin"]["width"] > ($maxSheet->getWidth()))
            )
            ||
            (
                ($gridFitting["cutSheet"]["gripMargin"]["position"] === "top")
                &&
                ($gridFitting["totalHeight"] + $gridFitting["cutSheet"]["gripMargin"]["height"]  > ($maxSheet->getHeight()))
            );
    }

    public function center(PositionedRectangleInterface $container, $area)
    {
        $unusedWidth = $container->getWidth() - $area["width"];
        $unusedHeight = $container->getHeight() - $area["height"];

        $area["x"] = $unusedWidth / 2;
        $area["y"] = $unusedHeight / 2;

        return $area;
    }

    public function placeOnto(PositionedRectangleInterface $container, $area)
    {
        $area["x"] = $container->getAbsoluteLeft() + $area["x"];
        $area["y"] = $container->getAbsoluteTop() + $area["y"];

        return $area;
    }

    public function centerOn(PositionedRectangleInterface $container, $area)
    {
        $area = $this->center($container, $area);
        $area = $this->placeOnto($container, $area);

        return $area;
    }


}