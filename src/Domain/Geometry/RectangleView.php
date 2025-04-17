<?php

namespace App\Domain\Geometry;

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
//        dump(__CLASS__);
        foreach ($children as $child) {
            $this->children[] = ($child->getViewModelClass())::fromEntity($child);
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