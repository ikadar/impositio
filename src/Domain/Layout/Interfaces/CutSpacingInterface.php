<?php

namespace App\Domain\Layout\Interfaces;

interface CutSpacingInterface
{
    public function getHorizontalSpacing(): float;
    public function getVerticalSpacing(): float;
}