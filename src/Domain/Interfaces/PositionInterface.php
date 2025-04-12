<?php

namespace App\Domain\Interfaces;

use App\Domain\Position;

interface PositionInterface
{
    public function getX(): CoordinateInterface;

    public function getY(): CoordinateInterface;

    public function setY(CoordinateInterface $y): PositionInterface;

    public function setX(CoordinateInterface $x): PositionInterface;

    public function reset(): PositionInterface;

    public function offset(Position $offset): PositionInterface;
}