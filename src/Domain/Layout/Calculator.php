<?php

namespace App\Domain\Layout;

use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\Machine;
use App\Domain\Geometry\AlignmentMode;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\Interfaces\GridFittingInterface;
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

    public function calculateGridFittings(
        MachineInterface $machine,
        RectangleInterface $pressSheet,
        InputSheetInterface $zone,
    ): array
    {

        // todo: max(zoneSize, minSheet)
        $minSheet = $this->printFactory->newRectangle(
            "maxSheet",
            0,
            0,
            $machine->getMinSheetDimensions()->getWidth(),
            $machine->getMinSheetDimensions()->getHeight()
        );
        $minSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        // todo: min(pressSheet, maxSheet)
        $maxSheet = $this->printFactory->newRectangle(
            "maxSheet",
            0,
            0,
            $machine->getMaxSheetDimensions()->getWidth(),
            $machine->getMaxSheetDimensions()->getHeight()
        );
        $maxSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        $rotatedZone = $this->printFactory->newInputSheet(
            "rotatedZone",
            0,
            0,
            $zone->getHeight(),
            $zone->getWidth()
        );
        $rotatedZone->setGripMarginSize($zone->getGripMarginSize());

        return array_merge(
            $this->calculateUnRotatedGridFittings(
                $machine,
                $zone,
                $pressSheet,
                $maxSheet,
                $minSheet,
                false
            ),
            $this->calculateUnRotatedGridFittings(
                $machine,
                $rotatedZone,
                $pressSheet,
                $maxSheet,
                $minSheet,
                true
            )
        );
    }

    protected function calculateUnRotatedGridFittings(
        Machine $machine,
        InputSheetInterface $zone,
        RectangleInterface $pressSheet,
        RectangleInterface $maxSheet,
        RectangleInterface $minSheet,
        $rotated
    ): array
    {
        $gridFittings = [];

        $exhaustiveGridFittings = $this->packer->calculateExhaustiveGridFitting($maxSheet, $zone, $rotated);

        foreach ($exhaustiveGridFittings as $layout) {

            $layout = $this->placeOnSheet($pressSheet, $minSheet, $machine->getGripMarginSize(), $layout, $zone);

            if ($this->layoutExceedsMaxSheet($layout, $maxSheet)) {
                continue;
            }

            $gridFittings[] = $layout;
        }

        return $gridFittings;
    }

    // -----------------

    public function calculateTrimLines(RectangleInterface $pressSheet, RectangleInterface $minSheet, RectangleInterface $cutSheet )
    {
        return [
            "top" => [
                "x" => 0,
                "y" => min($cutSheet->getTop(), $minSheet->getTop()),
                "length" => $pressSheet->getWidth(),
            ],
            "bottom" => [
                "x" => 0,
                "y" => max($cutSheet->getTop() + $cutSheet->getHeight(), $minSheet->getTop() + $minSheet->getHeight()),
                "length" => $pressSheet->getWidth(),
            ],
            "left" => [
                "x" => min($cutSheet->getLeft(), $minSheet->getLeft()),
                "y" => 0,
                "length" => $pressSheet->getHeight(),
            ],
            "right" => [
                "x" => max($cutSheet->getLeft() + $cutSheet->getWidth(), $minSheet->getLeft() + $minSheet->getWidth()),
                "y" => 0,
                "length" => $pressSheet->getHeight(),
            ]
        ];
    }

    // ------------------

    public function placeOnSheet(
        RectangleInterface $pressSheet,
        RectangleInterface $minSheet,
        $gripMarginSize,
        GridFittingInterface $layout, ////
        InputSheetInterface $zone ////
    ): GridFittingInterface
    {
        $totalWidth = $layout->getTotalWidth();
        $totalHeight = $layout->getTotalHeight();

        $cutSheet = $this->calculateCutSheet($pressSheet, $gripMarginSize, $totalWidth, $totalHeight, $minSheet, $zone);
        $layout->setCutSheet($cutSheet);
        $layout->setLayoutArea($this->calculateLayoutArea($totalWidth, $totalHeight, $cutSheet, $zone));
        $layout->setTrimLines($this->calculateTrimLines($pressSheet, $minSheet, $cutSheet));

        return $layout;
    }

    public function calculateLayoutArea($totalWidth, $totalHeight, InputSheetInterface $cutSheet, $zone): RectangleInterface
    {
        // take the content's dimensions including inner (content's) grip margins
        $layoutWithInnerGripMargins = [
            "width" => $totalWidth,
            "height" => $totalHeight,
        ];

        $gripMarginOverlap = max(0, ($zone->getGripMarginSize() - $cutSheet->getGripMarginSize()));

        // also take the content's dimensions excluding inner (content's) grip margins
        $layoutWithoutInnerGripMargins = [
            "width" => $layoutWithInnerGripMargins["width"] - $gripMarginOverlap,
            "height" => $layoutWithInnerGripMargins["height"] - $gripMarginOverlap,
        ];


        $bothOnLeft = (strtolower($zone->getGripMarginPosition()->name) === "left" && strtolower($cutSheet->getGripMarginPosition()->name) === "left");
        $bothOnTop = (strtolower($zone->getGripMarginPosition()->name) === "top" && strtolower($cutSheet->getGripMarginPosition()->name) === "top");

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

    public function layoutExceedsMaxSheet(GridFittingInterface $layout, RectangleInterface $maxSheet): bool
    {
        return
            (
                (strtolower($layout->getCutSheet()->getGripMarginPosition()->name) === "left")
                &&
                ($layout->getTotalWidth() + $layout->getCutSheet()->getChildById("gripMargin")->getWidth() > ($maxSheet->getWidth()))
            )
            ||
            (
                (strtolower($layout->getCutSheet()->getGripMarginPosition()->name) === "top")
                &&
                ($layout->getTotalHeight() + $layout->getCutSheet()->getChildById("gripMargin")->getHeight()  > ($maxSheet->getHeight()))
            );
    }

}