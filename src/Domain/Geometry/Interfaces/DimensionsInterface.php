<?php

namespace App\Domain\Geometry\Interfaces;

interface DimensionsInterface
{
    public function getWidth(): ?float;

    public function getHeight(): ?float;

    public function setWidth(float $width): DimensionsInterface;

    public function setHeight(float $height): DimensionsInterface;
}