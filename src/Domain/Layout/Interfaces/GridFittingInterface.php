<?php

namespace App\Domain\Layout\Interfaces;

use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\GridFitting;
use App\Domain\Sheet\Interfaces\InputSheetInterface;

interface GridFittingInterface
{
    public function getTiles(): array;
    public function setTiles(array $tiles): GridFitting;
    public function getSize(): string;
    public function setSize(string $size): GridFitting;
    public function isRotated(): bool;
    public function setRotated(bool $rotated): GridFitting;
    public function getCols(): int;
    public function setCols(int $cols): GridFitting;
    public function getRows(): int;
    public function setRows(int $rows): GridFitting;
    public function getTotalWidth(): float;
    public function setTotalWidth(float $totalWidth): GridFitting;
    public function getTotalHeight(): float;
    public function setTotalHeight(float $totalHeight): GridFitting;

    public function getCutSheet(): InputSheetInterface;
    public function setCutSheet(InputSheetInterface $cutSheet): GridFitting;
    public function getLayoutArea(): RectangleInterface;
    public function setLayoutArea(RectangleInterface $layoutArea): GridFitting;
    public function getTrimLines(): array;
    public function setTrimLines(array $trimLines): GridFitting;
}