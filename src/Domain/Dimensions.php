<?php

namespace App\Domain;

use App\Domain\Interfaces\DimensionsInterface;

class Dimensions implements DimensionsInterface
{
    public function __construct(
        protected ?float $width = null,
        protected ?float $height = null,
    )
    {
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setWidth(float $width): DimensionsInterface
    {
        $this->width = $width;
        return $this;
    }

    public function setHeight(float $height): DimensionsInterface
    {
        $this->height = $height;
        return $this;
    }

}