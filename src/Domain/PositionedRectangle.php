<?php

namespace App\Domain;

use App\Domain\Interfaces\DimensionsInterface;
use App\Domain\Interfaces\PlaneInterface;
use App\Domain\Interfaces\RectangleInterface;
use App\Domain\Interfaces\PositionedRectangleInterface;
use App\Domain\Interfaces\PositionInterface;

class PositionedRectangle extends AbstractRectangle implements PositionedRectangleInterface
{
    protected ?Rectangle $parent = null;

    public function __construct(
        protected ?PositionInterface   $position = null,
        protected ?DimensionsInterface $dimensions = null,
        protected GeometryFactory $geometryFactory,
        ?RectangleId $id,
    )
    {
        parent::__construct($id, $dimensions);
    }

    public function getDimensions(): ?DimensionsInterface
    {
        return $this->dimensions;
    }

    public function resetPosition(): static
    {
        $this->position = new Position(new Coordinate(), new Coordinate());
        return $this;
    }

    public function offsetByPosition(Position $offset): static
    {
        $this->getPosition()->offset($offset);
        return $this;
    }

    public function offset(float $x, float $y): static
    {
        $this->offsetByPosition(new Position(new Coordinate($x), new Coordinate($y)));
        return $this;
    }

    public function moveTo(PositionInterface $position): static
    {
        return $this->setPosition($position);
    }

    public function placeOnto(RectangleInterface|PlaneInterface $parentRectangle, Position|array $position): static
    {
        $parentRectangle->placeChild($this, $position);
        return $this;
    }

    public function setParent(?RectangleInterface $parent): static
    {
        $this->parent = $parent;
        return $this;
    }
    public function getParent(): ?RectangleInterface
    {
        return $this->parent;
    }

    public function getPosition(): ?PositionInterface
    {
        return $this->position;
    }

    public function setPosition(?PositionInterface $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getAbsolutePosition(): ?PositionInterface
    {
        $absolutePosition = $this->geometryFactory->copyPosition($this->getPosition());

        if ($this->getParent()) {
            $absolutePosition->offset($this->getParent()->getAbsolutePosition());
        }

        return $absolutePosition;
    }

    public function setAbsolutePosition(?PositionInterface $position): static
    {
        $parentAbsolutePosition = $this->getParent()->getAbsolutePosition();
        $this->setPosition($this->geometryFactory->newPosition(
            $position->getX()->getValue() - $parentAbsolutePosition->getX()->getValue(),
            $position->getY()->getValue() - $parentAbsolutePosition->getY()->getValue()
        ));
        return $this;
    }


}