<?php

namespace App\Domain;

use App\Domain\Interfaces\CoordinateInterface;
use App\Domain\Interfaces\PositionInterface;

class Position implements PositionInterface
{
    public function __construct(
        protected CoordinateInterface $x,
        protected CoordinateInterface $y,
    )
    {
    }

    public function getX(): CoordinateInterface
    {
        return $this->x;
    }

    public function getY(): CoordinateInterface
    {
        return $this->y;
    }

    public function setY(CoordinateInterface $y): PositionInterface
    {
        $this->y = $y;
        return $this;
    }

    public function setX(CoordinateInterface $x): PositionInterface
    {
        $this->x = $x;
        return $this;
    }

    public function reset(): PositionInterface
    {
        return $this->setX(new Coordinate(null))->setY(new Coordinate(null));
    }

    public function offset(Position $offset): PositionInterface
    {
        $this->getX()->offset($offset->getX());
        $this->getY()->offset($offset->getY());

        return $this;
    }
}