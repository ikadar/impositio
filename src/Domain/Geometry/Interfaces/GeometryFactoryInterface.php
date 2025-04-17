<?php

namespace App\Domain\Geometry\Interfaces;

interface GeometryFactoryInterface
{
    public function newPositionedRectangle(string $id, float $x, float $y, float $width, float $height): PositionedRectangleInterface;

    public function newRectangle(string $id, float $x, float $y, float $width, float $height): RectangleInterface;

    public function newPosition(float $x, float $y): PositionInterface;

    public function copyPosition(PositionInterface $position): PositionInterface;
}