<?php

namespace App\Domain;

use App\Domain\Interfaces\PlaneInterface;
use App\Domain\Interfaces\PositionedRectangleInterface;
use App\Domain\Interfaces\PositionInterface;
use App\Domain\Interfaces\RectangleInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class Plane implements PlaneInterface
{
    public function __construct(
        protected GeometryFactory           $geometryFactory,
        protected Rectangle                 $rectangle,
        protected PropertyAccessorInterface $propertyAccessor,
    )
    {
        $this->getRectangle()->setPosition($this->geometryFactory->newPosition(0, 0));
    }

    public function setId(string $id): static
    {
        $this->getRectangle()->setId($id);
        return $this;
    }

    public function getId(): string
    {
        return $this->getRectangle()->getId();
    }

    protected function getRectangle(): Rectangle
    {
        return $this->rectangle;
    }

    public function placeChild(PositionedRectangleInterface $child, PositionInterface $position): PositionedRectangleInterface
    {
        return $this->getRectangle()->placeChild($child, $position);
    }

    public function getTree(): array
    {
        $tree = [
            "_" => $this
        ];

        foreach ($this->getRectangle()->getChildren() as $loop => $child) {
            $tree[$child->getId()] = $child->getTree();
        }
        return $tree;
    }

    public function getChildById(string $id): RectangleInterface
    {
        return $this->propertyAccessor->getValue($this->getTree(), "{$id}[_]");
    }

    public function getChildren(): array
    {
        return $this->getRectangle()->getChildren();
    }

    public function dump(): ?string // move to Rectangle
    {
        return $this->getRectangle()->dump();
    }
}