<?php

namespace App\Domain\Geometry\Interfaces;

use App\Domain\Geometry\AlignmentMode;
use App\Domain\Geometry\Direction;
use App\Domain\Geometry\Position;
use App\Domain\Geometry\PositionedRectangle;

interface PositionedRectangleInterface extends AbstractRectangleInterface
{
    public function getDimensions(): ?DimensionsInterface;

    public function moveTo(PositionInterface $position): static;

    public function getPosition(): ?PositionInterface;

    public function setPosition(?PositionInterface $position): static;

    public function getWidth(): ?float;
    public function getHeight(): ?float;

    public function getAbsolutePosition(): ?PositionInterface;

    public function setAbsolutePosition(?PositionInterface $position): static;

    public function resetPosition(): static;

    public function offsetByPosition(Position $offset): static;

    public function offset(float $x, float $y): static;

    public function placeOnto(RectangleInterface|PlaneInterface $parentRectangle, Position|array $position): static;

    public function setParent(?RectangleInterface $parent): static;
    public function getParent(): ?RectangleInterface;

    public function getOffset(PositionInterface $to): ?PositionInterface;

    public function alignTo(PositionedRectangleInterface $to, AlignmentMode $alignmentMode, ?RectangleInterface $what = null): static;

    public function resize(DimensionsInterface $newDimensions, Direction $direction): self;

    public function getLeft(): float;
    public function getCenter(): float;
    public function getRight(): float;
    public function getTop(): float;
    public function getMiddle(): float;
    public function getBottom(): float;
    public function getAbsoluteLeft(): float;
    public function getAbsoluteCenter(): float;
    public function getAbsoluteRight(): float;
    public function getAbsoluteTop(): float;
    public function getAbsoluteMiddle(): float;
    public function getAbsoluteBottom(): float;

    public function stretchX($left, $right): static;
    public function stretchY($top, $bottom): static;
    public function stretchXTo(PositionedRectangleInterface $rectangle, ?float $gap = 0): static;
    public function stretchYTo(PositionedRectangleInterface $rectangle, ?float $gap = 0): static;
    public function stretchTo(PositionedRectangleInterface $rectangle, ?float $gap = 0): static;
}