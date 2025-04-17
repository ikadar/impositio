<?php

namespace App\Domain\Sheet;

use App\Domain\Geometry\Direction;
use App\Domain\Geometry\Rectangle;
use App\Domain\Sheet\Interfaces\InputSheetInterface;

class InputSheetView
{
    public array $children = [];

    public function __construct(
        public string $id,
        public ?float $x,
        public ?float $y,
        public ?float $width,
        public ?float $height,
        public ?string $gripMarginPosition,
        array $children,
    ) {
        foreach ($children as $child) {
            $this->children[] = ($child->getViewModelClass())::fromEntity($child);
        }
    }

    public static function fromEntity(InputSheetInterface $inputSheet): self
    {
        return new self(
            $inputSheet->getId(),
            $inputSheet->getPosition()->getX()->getValue(),
            $inputSheet->getPosition()->getY()->getValue(),
            $inputSheet->getDimensions()->getWidth(),
            $inputSheet->getDimensions()->getHeight(),
            strtolower($inputSheet->getGripMarginPosition()->name),
            $inputSheet->getChildren()
        );
    }

}