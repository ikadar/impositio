<?php

namespace App\Domain\Interfaces;

use App\Domain\Position;

interface PositionedRectangleInterface extends AbstractRectangleInterface
{
    public function getDimensions(): ?DimensionsInterface;

    public function setDimensions(?DimensionsInterface $dimensions): static;

    public function moveTo(PositionInterface $position): static;

    public function getPosition(): ?PositionInterface;

    public function setPosition(?PositionInterface $position): static;

    public function getAbsolutePosition(): ?PositionInterface;

    public function setAbsolutePosition(?PositionInterface $position): static;

    public function resetPosition(): static;

    public function offsetByPosition(Position $offset): static;

    public function offset(float $x, float $y): static;

    public function placeOnto(RectangleInterface|PlaneInterface $parentRectangle, Position|array $position): static;

    public function setParent(?RectangleInterface $parent): static;
    public function getParent(): ?RectangleInterface;

}