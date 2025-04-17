<?php

namespace App\Domain\Geometry;

use App\Domain\Geometry\Interfaces\AbstractRectangleInterface;
use App\Domain\Geometry\Interfaces\DimensionsInterface;

abstract class AbstractRectangle implements AbstractRectangleInterface
{

    protected string $id;

    public function __construct(
        protected DimensionsInterface $dimensions,
        ?RectangleId $id = null,
//        protected ?DimensionsInterface $dimensions = null
    )
    {
        $this->setId($id);
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDimensions(): ?DimensionsInterface
    {
        return $this->dimensions;
    }

    public function setDimensions(?DimensionsInterface $dimensions): static
    {
        $this->dimensions = $dimensions;
        return $this;
    }

}