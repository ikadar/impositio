<?php

namespace App\Domain;

class RectangleView
{
    public array $children = [];
    public function __construct(
        public string $id,
        public ?float $x,
        public ?float $y,
        public ?float $width,
        public ?float $height,
        array $children,
    ) {
        foreach ($children as $child) {
            $this->children[] = self::fromEntity($child);
        }
    }

    public static function fromEntity(Rectangle $rectangle): self
    {
        return new self(
            $rectangle->getId(),
            $rectangle->getPosition()->getX()->getValue(),
            $rectangle->getPosition()->getY()->getValue(),
            $rectangle->getDimensions()->getWidth(),
            $rectangle->getDimensions()->getHeight(),
            $rectangle->getChildren()
        );
    }
}