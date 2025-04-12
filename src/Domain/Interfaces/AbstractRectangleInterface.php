<?php

namespace App\Domain\Interfaces;

interface AbstractRectangleInterface
{
    public function setDimensions(?DimensionsInterface $dimensions): static;

    public function setId(string $id): static;
    public function getId(): string;
}