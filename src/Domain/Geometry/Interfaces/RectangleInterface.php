<?php

namespace App\Domain\Geometry\Interfaces;

interface RectangleInterface extends AbstractRectangleInterface, PositionedRectangleInterface
{
    public function moveTo(PositionInterface $position): static;

    public function placeChild(PositionedRectangleInterface $child, PositionInterface $position): PositionedRectangleInterface;

    public function getChildren(): array;

    public function getTree(): array;

    public function getChildById(string $id): PositionedRectangleInterface;

    public function removeChild(PositionedRectangleInterface $child): static;

    public function dump(): ?string;

    public function toJson(): ?string;

}