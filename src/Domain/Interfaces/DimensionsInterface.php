<?php

namespace App\Domain\Interfaces;

use App\Domain\Dimensions;

interface DimensionsInterface
{
    public function getWidth(): ?float;

    public function getHeight(): ?float;

    public function setWidth(float $width): DimensionsInterface;

    public function setHeight(float $height): DimensionsInterface;
}