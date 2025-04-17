<?php

namespace App\Domain\Sheet;

use App\Domain\Geometry\Coordinate;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\GeometryFactory;
use App\Domain\Geometry\MyCliDumper;
use App\Domain\Geometry\Position;
use App\Domain\Geometry\RectangleId;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PrintFactoryInterface;
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

    public function newInputSheet (string $id, float $x, float $y, float $width, float $height): InputSheetInterface
    {
        return new InputSheet(
            new Position(new Coordinate($x), new Coordinate($y)),
            new Dimensions($width, $height),
            $this,
            (new RectangleId())->setValue($id),
        );
    }

}