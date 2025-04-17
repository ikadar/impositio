<?php

namespace App\Domain\Geometry;

use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Geometry\Interfaces\PlaneInterface;
use App\Domain\Geometry\Interfaces\PositionedRectangleInterface;
use App\Domain\Geometry\Interfaces\PositionInterface;
use App\Domain\Geometry\Interfaces\RectangleInterface;

class PositionedRectangle extends AbstractRectangle implements PositionedRectangleInterface
{
    protected ?Rectangle $parent = null;

    public function __construct(
        protected PositionInterface   $position,
        protected DimensionsInterface $dimensions,
        protected GeometryFactory $geometryFactory,
        ?RectangleId $id,
    )
    {
        parent::__construct($dimensions, $id);
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

    public function getOffset(PositionInterface $to): ?PositionInterface
    {
        return $this->geometryFactory->newPosition(
            $this->getPosition()->getX()->getValue() - $to->getX()->getValue(),
            $this->getPosition()->getY()->getValue() - $to->getY()->getValue()
        );
    }

    public function alignTo(PositionedRectangleInterface $to, AlignmentMode $alignmentMode, ?RectangleInterface $what = null): static
    {

        $alignmentPoints = $alignmentMode->alignmentPoints();

        if ($what === null) {
            $what = $this;
        }

        $this->offset(
            $to->getAbsolutePosition()->getX()->getValue() // Left X of $to
            + $to->getDimensions()->getWidth() * $alignmentPoints["to"]->xFactor() // alignment point X offset of $to
            - $what->getAbsolutePosition()->getX()->getValue() // Left X of $what
            - $what->getDimensions()->getWidth() * $alignmentPoints["what"]->xFactor(), // alignment point X offset of $what

            $to->getAbsolutePosition()->getY()->getValue()  // Top Y of $to
            + $to->getDimensions()->getHeight() * $alignmentPoints["to"]->yFactor() // alignment point Y offset of $to
            - $what->getAbsolutePosition()->getY()->getValue() // Top Y of $what
            - $what->getDimensions()->getHeight() * $alignmentPoints["what"]->yFactor(), // alignment point Y offset of $what
        );

        return $this;
    }

    public function resize(DimensionsInterface $newDimensions, Direction $direction): self
    {
        $dX = $newDimensions->getWidth() - $this->getDimensions()->getWidth();
        $dY = $newDimensions->getHeight() - $this->getDimensions()->getHeight();

        $this->setDimensions($newDimensions);
        $offset = $this->geometryFactory->newPosition($dX * $direction->xFactor(), $dY * $direction->yFactor());
        $this->offsetByPosition($offset);

        return $this;
    }

    public function getLeft(): float
    {
        return $this->getPosition()->getX()->getValue();
    }
    public function getCenter(): float
    {
        return $this->getPosition()->getX()->getValue() + ($this->getDimensions()->getWidth() / 2);
    }
    public function getRight(): float
    {
        return $this->getPosition()->getX()->getValue() + $this->getDimensions()->getWidth();
    }
    public function getTop(): float
    {
        return $this->getPosition()->getY()->getValue();
    }
    public function getMiddle(): float
    {
        return $this->getPosition()->getY()->getValue() + ($this->getDimensions()->getHeight() / 2);
    }
    public function getBottom(): float
    {
        return $this->getPosition()->getY()->getValue() + $this->getDimensions()->getHeight();
    }

    public function getAbsoluteLeft(): float
    {
        return $this->getAbsolutePosition()->getX()->getValue();
    }
    public function getAbsoluteCenter(): float
    {
        return $this->getAbsolutePosition()->getX()->getValue() + ($this->getDimensions()->getWidth() / 2);
    }
    public function getAbsoluteRight(): float
    {
        return $this->getAbsolutePosition()->getX()->getValue() + $this->getDimensions()->getWidth();
    }
    public function getAbsoluteTop(): float
    {
        return $this->getAbsolutePosition()->getY()->getValue();
    }
    public function getAbsoluteMiddle(): float
    {
        return $this->getAbsolutePosition()->getY()->getValue() + ($this->getDimensions()->getHeight() / 2);
    }
    public function getAbsoluteBottom(): float
    {
        return $this->getAbsolutePosition()->getY()->getValue() + $this->getDimensions()->getHeight();
    }

    public function stretchX($left, $right): static
    {
        $this->setDimensions(new Dimensions($right - $left, $this->getDimensions()->getHeight()));
        return $this;
    }
    public function stretchY($top, $bottom): static
    {
        $this->setDimensions(new Dimensions($this->getDimensions()->getWidth(), $bottom - $top));
        return $this;
    }

    public function stretchXTo(PositionedRectangleInterface $rectangle, ?float $gap = 0): static
    {
        return $this->stretchX($rectangle->getAbsoluteLeft() + $gap, $rectangle->getAbsoluteRight());
    }

    public function stretchYTo(PositionedRectangleInterface $rectangle, ?float $gap = 0): static
    {
        return $this->stretchY($rectangle->getAbsoluteTop() + $gap, $rectangle->getAbsoluteBottom());
    }

    public function stretchTo(PositionedRectangleInterface $rectangle, ?float $gap = 0): static
    {
        $this->stretchXTo($rectangle, $gap);
        $this->stretchYTo($rectangle, $gap);
        return $this;
    }

    public function getWidth(): ?float
    {
        return $this->getDimensions()->getWidth();
    }
    public function getHeight(): ?float
    {
        return $this->getDimensions()->getHeight();
    }


}