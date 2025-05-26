<?php

namespace App\Domain\Layout;

use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\Machine;
use App\Domain\Geometry\AlignmentMode;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Direction;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\Interfaces\CalculatorInterface;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Layout\Interfaces\PackerInterface;
use App\Domain\Sheet\GripMarginPosition;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\PrintFactory;

class Calculator implements CalculatorInterface
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
        InputSheetInterface $zone, // Tile?
    ): array
    {
        // todo: max(zoneSize, minSheet)
        $minSheet = $machine->getMinSheetRectangle();
        $minSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        // todo: min(pressSheet, maxSheet)
        $maxSheet = $machine->getMaxSheetRectangle();
        $maxSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        $rotatedZone = $this->printFactory->newInputSheet(
            "rotatedZone",
            0,
            0,
            $zone->getHeight(),
            $zone->getWidth()
        );
        $rotatedZone->setGripMarginSize($zone->getGripMarginSize());
        $rotatedZone->setContentType($zone->getContentType());


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

            if ($this->layoutExceedsMaxSheet($layout, $pressSheet)) {
                continue;
            }

            $layout->setExplanation([
                "machine" => [
                    "name" => $machine->getId(),
                    "minSheet" => $machine->getMinSheetDimensions(),
                    "maxSheet" => $machine->getMaxSheetDimensions(),
                ]
            ]);

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

//        return [
//            "top" => [
//                "x" => 0,
//                "y" => min($cutSheet->getChildById("usableArea")->getAbsoluteTop(), $minSheet->getTop()),
//                "length" => $pressSheet->getWidth(),
//            ],
//            "bottom" => [
//                "x" => 0,
//                "y" => max($cutSheet->getTop() + $cutSheet->getHeight(), $minSheet->getTop() + $minSheet->getHeight()),
//                "length" => $pressSheet->getWidth(),
//            ],
//            "left" => [
//                "x" => min($cutSheet->getChildById("usableArea")->getAbsoluteLeft(), $minSheet->getLeft()),
//                "y" => 0,
//                "length" => $pressSheet->getHeight(),
//            ],
//            "right" => [
//                "x" => max($cutSheet->getLeft() + $cutSheet->getWidth(), $minSheet->getLeft() + $minSheet->getWidth()),
//                "y" => 0,
//                "length" => $pressSheet->getHeight(),
//            ]
//        ];


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
        // todo: instead
        // - calculate layout area (totalWidth, totalHeight)
        // - place it to the cut sheet with proper alignment
        // - resize cutSheet

        $cutSheet = $this->calculateCutSheet($pressSheet, $gripMarginSize, $layout, $minSheet);
        $layout->setCutSheet($cutSheet);
        $layout->setLayoutArea($this->calculateLayoutArea($layout, $cutSheet, $zone, $minSheet, $pressSheet));
        $layout->setTrimLines($this->calculateTrimLines($pressSheet, $minSheet, $cutSheet));

        return $layout;
    }

    public function calculateCutSheet(RectangleInterface $pressSheet, $gripMarginSize, $layout, RectangleInterface $minSheet): InputSheetInterface
    {
        $cutSheet = $this->printFactory->newInputSheet(
            "cutSheet",
            0,
            0,
            max($layout->getTotalWidth(), $minSheet->getWidth()),
            max($layout->getTotalHeight(), $minSheet->getHeight()),
        );
        $cutSheet->setGripMarginSize($gripMarginSize);

        $originalDimensions = new Dimensions(
            max($layout->getTotalWidth(), $minSheet->getWidth()),
            max($layout->getTotalHeight(), $minSheet->getHeight())
        );

        // Ha a cutSheet grip marginja felul van
        if (
            ($cutSheet->getGripMarginPosition() === GripMarginPosition::Top)
            &&
            ($cutSheet->getChildById("usableArea")->getHeight() < $layout->getTotalHeight())
        ) {
            // megnöveljük a cutSheet magasságát a grip margin méretével
            $cutSheet->resize(
                new Dimensions(
                    $cutSheet->getWidth(),
                    $cutSheet->getHeight() + $gripMarginSize
                ),
                Direction::BottomCenter
            );
        }

        if (
            ($cutSheet->getGripMarginPosition() === GripMarginPosition::Left)
            &&
            ($cutSheet->getChildById("usableArea")->getWidth() < $layout->getTotalWidth())
        ) {
            $cutSheet->resize(
                new Dimensions(
                    $cutSheet->getWidth() + $gripMarginSize,
                    $cutSheet->getHeight()),
                Direction::MiddleRight
            );
        }

        $cutSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        return $cutSheet;

    }

    public function calculateLayoutArea(GridFittingInterface $layout, InputSheetInterface $cutSheet, $zone, $minSheet, $pressSheet): RectangleInterface
    {
        $firstTile = $layout->getTiles()[0]->getTileWithSpacing();

        $layoutArea = $this->printFactory->newInputSheet(
            "layoutArea",
            0,
            0,
            $layout->getTotalWidth(),
            $layout->getTotalHeight(),
        );
        $layoutArea->setGripMarginSize($firstTile->getGripMarginSize());
        $layoutArea->placeOnto($cutSheet, $this->printFactory->newPosition(0, 0));

        $layoutArea->alignTo($cutSheet->getChildById("usableArea"), AlignmentMode::MiddleCenterToMiddleCenter);

        // Most a layout a cut sheet usable area-ra van középre igazítva

        $bothGripMarginsAreOnTheTop = (
            ($cutSheet->getGripMarginPosition() === GripMarginPosition::Top)
            &&
            ($firstTile->getGripMarginPosition() === GripMarginPosition::Top)
        );

        $bothGripMarginsAreOnTheLeft = (
            ($cutSheet->getGripMarginPosition() === GripMarginPosition::Left)
            &&
            ($firstTile->getGripMarginPosition() === GripMarginPosition::Left)
        );

        if ($bothGripMarginsAreOnTheTop && ($firstTile->getGripMarginSize() > 0)) {

            $alignment = ($firstTile->getGripMarginSize() >= $cutSheet->getGripMarginSize())
                ? AlignmentMode::TopCenterToTopCenter
                : AlignmentMode::BottomCenterToBottomCenter
            ;

            $moveUp = min($firstTile->getGripMarginSize(), $cutSheet->getGripMarginSize());

            $cutSheet->resize(
                new Dimensions(
                    max($cutSheet->getWidth(), $minSheet->getWidth()),
                    max($cutSheet->getHeight() - $moveUp, $minSheet->getHeight()),
                ),
                Direction::BottomCenter
            );

            $layoutArea->alignTo($cutSheet->getChildById("gripMargin"), $alignment, $layoutArea->getChildById("gripMargin"));
        }

        if ($bothGripMarginsAreOnTheLeft && ($firstTile->getGripMarginSize() > 0)) {

            $alignment = ($firstTile->getGripMarginSize() >= $cutSheet->getGripMarginSize())
                ? AlignmentMode::MiddleLeftToMiddleLeft
                : AlignmentMode::MiddleRightToMiddleRight
            ;

            $moveLeft = min($firstTile->getGripMarginSize(), $cutSheet->getGripMarginSize());

            $cutSheet->resize(
                new Dimensions(
                    max($cutSheet->getWidth() - $moveLeft, $minSheet->getWidth()),
                    max($cutSheet->getHeight(), $minSheet->getHeight()),
                ),
                Direction::BottomCenter
            );

            $layoutArea->alignTo($cutSheet->getChildById("gripMargin"), $alignment, $layoutArea->getChildById("gripMargin"));
        }

        $cutSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        return $layoutArea;
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
