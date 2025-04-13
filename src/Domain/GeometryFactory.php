<?php

namespace App\Domain;

use App\Domain\Interfaces\RectangleInterface;
use App\Domain\Interfaces\PositionedRectangleInterface;
use App\Domain\Interfaces\GeometryFactoryInterface;
use App\Domain\Interfaces\PositionInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class GeometryFactory implements GeometryFactoryInterface
{
    public function __construct(
        private PropertyAccessorInterface $propertyAccessor,
        private MyCliDumper               $dumper,
    )
    {
    }

    public function newPositionedRectangle($id, float $x, float $y, float $width, float $height): PositionedRectangleInterface
    {
        return
            new PositionedRectangle(
                new Position(new Coordinate($x), new Coordinate($y)),
                new Dimensions($width, $height),
                $this,
                (new RectangleId())->setValue($id)
            );
    }

    public function newRectangle($id, float $x, float $y, float $width, float $height): RectangleInterface
    {
        return new Rectangle(
            new Position(new Coordinate($x), new Coordinate($y)),
            new Dimensions($width, $height),
            $this,
            (new RectangleId())->setValue($id),
        );
    }

    public function newPosition(float $x, float $y): PositionInterface
    {
        return (new Position(new Coordinate($x), new Coordinate($y)));
    }

    public function copyPosition(PositionInterface $position): PositionInterface
    {
        return $this->newPosition(
            $position->getX()->getValue(),
            $position->getY()->getValue(),
        );

    }

    public function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor;
    }

    public function getDumper(): MyCliDumper
    {
        return $this->dumper;
    }

}