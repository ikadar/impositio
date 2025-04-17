<?php

namespace App\Domain\Geometry\Interfaces;

use App\Domain\Geometry\Position;

interface PositionInterface
{
    public function getX(): CoordinateInterface;

    public function getY(): CoordinateInterface;

    public function setY(CoordinateInterface $y): PositionInterface;

    public function setX(CoordinateInterface $x): PositionInterface;

    public function reset(): PositionInterface;

    public function offset(Position $offset): PositionInterface;
}