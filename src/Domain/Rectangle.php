<?php

namespace App\Domain;

use App\Domain\Interfaces\DimensionsInterface;
use App\Domain\Interfaces\RectangleInterface;
use App\Domain\Interfaces\PositionedRectangleInterface;
use App\Domain\Interfaces\PositionInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class Rectangle extends PositionedRectangle implements RectangleInterface
{
    protected array $children = [];

    public function __construct(
        PositionInterface $position,
        DimensionsInterface $dimensions,
        GeometryFactory $geometryFactory,
        ?RectangleId $id,
    )
    {
        parent::__construct($position, $dimensions, $geometryFactory, $id);
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function __debugInfo(): array
    {
        return [
            'width' => "Q",
            'height' => "D",
        ];
    }
    public function placeChild(PositionedRectangleInterface $child, PositionInterface $position): PositionedRectangleInterface
    {
        if ($child->getParent()) {
            $child->getParent()->removeChild($child);
        }
        $child->setParent($this);
        $this->children[$child->getId()] = $child->moveTo($position);
        return $child;
    }

    public function getTree(): array
    {
        $tree = [
            "_" => $this
        ];

        foreach ($this->getChildren() as $loop => $child) {
            $tree[$child->getId()] = $child->getTree();
        }
        return $tree;
    }

    public function getChildById(string $id): PositionedRectangleInterface
    {
        $id = sprintf("[%s]", implode("][", explode(".", $id)));
        return $this->geometryFactory->getPropertyAccessor()->getValue($this->getTree(), "{$id}[_]");
    }

    public function removeChild(PositionedRectangleInterface $child): static
    {
        unset($this->children[$child->getId()]);
        return $this;
    }

//    public function alignTo(RectangleInterface $to, ?RectangleInterface $relativeTo = null): static
//    {
//        if ($relativeTo === null) {
//            $relativeTo = $this;
//        }
//
//        $offset = $relativeTo->getOffset($to);
//        dump($offset);
//
////        dump($to->getAbsolutePosition());
//        $this->setAbsolutePosition($to->getAbsolutePosition());
//
//        return $this;
//    }

    public function dump(): ?string // move to Rectangle
    {
        return $this->geometryFactory->getDumper()->d($this);
    }

    public function toJson(): ?string
    {
        return $this->geometryFactory->getSerializer()->serialize(RectangleView::fromEntity($this), "json", [
            'circular_reference_handler' => function ($object) {
                return $object->getId(); // or `spl_object_hash($object)` if there's no ID
            },
            'json_encode_options' => JSON_PRETTY_PRINT,
        ]);
    }


}