<?php

namespace App\Domain\Sheet;

use App\Domain\Geometry\Coordinate;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\GeometryFactory;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Geometry\MyCliDumper;
use App\Domain\Geometry\Position;
use App\Domain\Geometry\RectangleId;
use App\Domain\Layout\CutSpacing;
use App\Domain\Layout\GridFitting;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Layout\Interfaces\TileInterface;
use App\Domain\Layout\Tile;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use App\Domain\Sheet\Interfaces\SheetInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\SerializerInterface;

class PrintFactory extends GeometryFactory // implements PrintFactoryInterface
{
    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        MyCliDumper $dumper,
        SerializerInterface $serializer,
    )
    {
        parent::__construct($propertyAccessor, $dumper, $serializer);
    }

    public function newSheet (string $id, float $x, float $y, float $width, float $height): SheetInterface
    {
        return new Sheet(
            new Position(new Coordinate($x), new Coordinate($y)),
            new Dimensions($width, $height),
            $this,
            (new RectangleId())->setValue($id),
        );
    }

    public function newPressSheet (string $id, float $x, float $y, float $width, float $height, float $price): PressSheetInterface
    {
        return new PressSheet(
            new Position(new Coordinate($x), new Coordinate($y)),
            new Dimensions($width, $height),
            $this,
            (new RectangleId())->setValue($id),
            $price
        );
    }

    public function newInputSheet (string $id, float $x, float $y, float $width, float $height): InputSheetInterface
    {
        return new InputSheet(
            new Position(new Coordinate($x), new Coordinate($y)),
            new Dimensions($width, $height),
            $this,
            (new RectangleId())->setValue($id),
        );
    }

    public function newTile (
        int $colIndex,
        int $rowIndex,
        CutSpacing $cutSpacing,
        RectangleInterface $tileRect,
    ): TileInterface
    {
        $gridPosition = [
            "x" => $colIndex,
            "y" => $rowIndex,
        ];

        $innerTile = $this->newInputSheet(
            sprintf("%dx%d", $colIndex, $rowIndex),

            (($colIndex + 1) * $cutSpacing->getHorizontalSpacing()) +
            (($colIndex) * $cutSpacing->getHorizontalSpacing()) +
            $colIndex * ($tileRect->getWidth()),

            (($rowIndex + 1) * $cutSpacing->getVerticalSpacing()) +
            (($rowIndex) * $cutSpacing->getVerticalSpacing()) +
            $rowIndex * ($tileRect->getHeight()),

            $tileRect->getWidth(),
            $tileRect->getHeight(),
        );
        $innerTile->setGripMarginSize($tileRect->getGripMarginSize());

        $tileWithSpacing = $this->newInputSheet(
            sprintf("WC%dx%d", $colIndex, $rowIndex),
            ($colIndex * ($tileRect->getWidth() + (2 * $cutSpacing->getHorizontalSpacing()))),
            $rowIndex * ($tileRect->getHeight() + (2 * $cutSpacing->getVerticalSpacing())),
            $tileRect->getWidth() + (2 * $cutSpacing->getHorizontalSpacing()),
            $tileRect->getHeight() + (2 * $cutSpacing->getVerticalSpacing()),
        );
        $tileWithSpacing->setGripMarginSize($tileRect->getGripMarginSize());


        return new Tile($gridPosition, $innerTile, $tileWithSpacing);
    }

    public function newGridFitting ($tiles, $colIndex, $rowIndex, $rotated, InputSheetInterface $tileRect, $spacing): GridFittingInterface
    {
        return new GridFitting(
            $tiles,
            sprintf("%dx%d", $colIndex, $rowIndex),
            $rotated,
            $colIndex,
            $rowIndex,
            $spacing,
            $this
        );
    }

    public function getCutSpacing(): CutSpacing
    {
        // todo: move to config
        return  new CutSpacing(
            10,
            10,
        );
    }
}