<?php

namespace App\Domain\Geometry;

use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Geometry\Interfaces\PositionedRectangleInterface;
use App\Domain\Geometry\Interfaces\PositionInterface;
use App\Domain\Geometry\Interfaces\RectangleInterface;

class Rectangle extends PositionedRectangle implements RectangleInterface
{
    protected array $children = [];
    protected string $viewModelClass = RectangleView::class;

    public function __construct(
        PositionInterface $position,
        DimensionsInterface $dimensions,
        GeometryFactory $geometryFactory,
        ?RectangleId $id,
    )
    {
        parent::__construct($position, $dimensions, $geometryFactory, $id);
    }

    public function getViewModelClass(): string
    {
        return $this->viewModelClass;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

//    public function __debugInfo(): array
//    {
//        return [
//            'width' => "Q",
//            'height' => "D",
//        ];
//    }
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

    public function toJson2(bool $withChildren = false): ?string
    {
        $viewObj = ($this->viewModelClass)::fromEntity($this);
        if ($withChildren === false) {
            $viewObj->children = [];
        }
        $serialized = $this->geometryFactory->getSerializer()->serialize($viewObj, "json", [
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            },
            'circular_reference_limit' => 1,
            'json_encode_options' => JSON_PRETTY_PRINT,
        ]);

        return $serialized;
    }

    public function toJson(bool $withChildren = false): ?string
    {
        $viewObject = ($this->viewModelClass)::fromEntity($this);
        $array = [
            "id" => $viewObject->id,
            "viewObject" => $viewObject->x,
            "y" => $viewObject->y,
            "width" => $viewObject->width,
            "height" => $viewObject->height,
//            "children" => $viewObject->children,
//            "gripMarginPosition" => "left",
        ];

        if ($withChildren) {
            $array["children"] = $viewObject->children;
        }


        return json_encode($array, JSON_PRETTY_PRINT);
    }


}