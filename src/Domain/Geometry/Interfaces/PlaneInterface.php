<?php

namespace App\Domain\Geometry\Interfaces;

interface PlaneInterface
{
    public function setId(string $id): static;
    public function getId(): string;

    public function placeChild(PositionedRectangleInterface $child, PositionInterface $position): PositionedRectangleInterface;

    public function getTree(): array;

    public function getChildById(string $id): RectangleInterface;

    public function dump(): ?string;

    public function toJson(): ?string;
}