<?php

namespace App\Domain\Sheet\Interfaces;

use App\Domain\Geometry\Direction;
use App\Domain\Sheet\GripMarginPosition;
use App\Domain\Sheet\InputSheet;

interface InputSheetInterface extends SheetInterface
{
    public function setGripMarginSize(float $size): static;
    public function getGripMarginSize(): float;

    public function getGripMarginPosition(): GripMarginPosition;

    public function getContentType(): string;

    public function setContentType(string $contentType): InputSheet;
}