<?php

namespace App\Domain\Layout;

use App\Domain\Layout\Interfaces\CutSpacingInterface;

class CutSpacing implements Interfaces\CutSpacingInterface
{
    public function __construct(
        protected float $horizontalSpacing,
        protected float $verticalSpacing,
    )
    {
    }

    public function getHorizontalSpacing(): float
    {
        return $this->horizontalSpacing;
    }

    public function getVerticalSpacing(): float
    {
        return $this->verticalSpacing;
    }
}