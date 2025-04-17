<?php

namespace App\Domain\Layout;

use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;

class GridFitting implements Interfaces\GridFittingInterface
{
    protected InputSheetInterface $cutSheet;
    protected RectangleInterface $layoutArea;
    protected array $trimLines;

    public function __construct(
        protected array $tiles,
        protected string $size,
        protected bool $rotated,
        protected int $cols,
        protected int $rows,
        protected float $totalWidth,
        protected float $totalHeight,
    )
    {
    }

    public function getTiles(): array
    {
        return $this->tiles;
    }

    public function setTiles(array $tiles): GridFitting
    {
        $this->tiles = $tiles;
        return $this;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function setSize(string $size): GridFitting
    {
        $this->size = $size;
        return $this;
    }

    public function isRotated(): bool
    {
        return $this->rotated;
    }

    public function setRotated(bool $rotated): GridFitting
    {
        $this->rotated = $rotated;
        return $this;
    }

    public function getCols(): int
    {
        return $this->cols;
    }

    public function setCols(int $cols): GridFitting
    {
        $this->cols = $cols;
        return $this;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function setRows(int $rows): GridFitting
    {
        $this->rows = $rows;
        return $this;
    }

    public function getTotalWidth(): float
    {
        return $this->totalWidth;
    }

    public function setTotalWidth(float $totalWidth): GridFitting
    {
        $this->totalWidth = $totalWidth;
        return $this;
    }

    public function getTotalHeight(): float
    {
        return $this->totalHeight;
    }

    public function setTotalHeight(float $totalHeight): GridFitting
    {
        $this->totalHeight = $totalHeight;
        return $this;
    }

    public function getCutSheet(): InputSheetInterface
    {
        return $this->cutSheet;
    }

    public function setCutSheet(InputSheetInterface $cutSheet): GridFitting
    {
        $this->cutSheet = $cutSheet;
        return $this;
    }

    public function getLayoutArea(): RectangleInterface
    {
        return $this->layoutArea;
    }

    public function setLayoutArea(RectangleInterface $layoutArea): GridFitting
    {
        $this->layoutArea = $layoutArea;
        return $this;
    }

    public function getTrimLines(): array
    {
        return $this->trimLines;
    }

    public function setTrimLines(array $trimLines): GridFitting
    {
        $this->trimLines = $trimLines;
        return $this;
    }

    public function toArray($pressSheet, $minSheet, $maxSheet)
    {
        $array = [
            "cols" => $this->getCols(),
            "rows" => $this->getRows(),

            "cutSheet" => [
                "gripMargin" => [
                    "size" => $this->getCutSheet()->getGripMarginSize(),
                    "position" => strtolower($this->getCutSheet()->getGripMarginPosition()->name),
                    "x" => $this->getCutSheet()->getChildById("gripMargin")->getAbsoluteLeft(),
                    "y" => $this->getCutSheet()->getChildById("gripMargin")->getAbsoluteTop(),
                    "width" => $this->getCutSheet()->getChildById("gripMargin")->getWidth(),
                    "height" => $this->getCutSheet()->getChildById("gripMargin")->getHeight(),
                ],
                "usableArea" => [
                    "x" => $this->getCutSheet()->getChildById("usableArea")->getAbsoluteLeft(),
                    "y" => $this->getCutSheet()->getChildById("usableArea")->getAbsoluteTop(),
                    "width" => $this->getCutSheet()->getChildById("usableArea")->getWidth(),
                    "height" => $this->getCutSheet()->getChildById("usableArea")->getHeight(),
                ],
                "x" => $this->getCutSheet()->getAbsoluteLeft(),
                "y" => $this->getCutSheet()->getAbsoluteTop(),
                "width" => $this->getCutSheet()->getWidth(),
                "height" => $this->getCutSheet()->getHeight(),
            ],

            "firstTile" => [
                "x" => $this->getTiles()[0]->getInnerTile()->getLeft(),
                "y" => $this->getTiles()[0]->getInnerTile()->getTop(),
                "width" => $this->getTiles()[0]->getInnerTile()->getWidth(),
                "height" => $this->getTiles()[0]->getInnerTile()->getHeight()
            ],

            "firstTileWithCutBuffer" => [
                "x" => $this->getTiles()[0]->getTileWithSpacing()->getLeft(),
                "y" => $this->getTiles()[0]->getTileWithSpacing()->getTop(),
                "width" => $this->getTiles()[0]->getTileWithSpacing()->getWidth(),
                "height" => $this->getTiles()[0]->getTileWithSpacing()->getHeight()
            ],

            "layoutArea" => [
                "x" => $this->getLayoutArea()->getAbsoluteLeft(),
                "y" => $this->getLayoutArea()->getAbsoluteTop(),
                "width" => $this->getLayoutArea()->getWidth(),
                "height" => $this->getLayoutArea()->getHeight(),
            ],

            "rotated" => $this->isRotated(),

            "totalWidth" => $this->getTotalWidth(),
            "totalHeight" => $this->getTotalHeight(),

            "trimLines" => $this->getTrimLines(),

            "maxSheet" => json_decode($maxSheet->toJson(), true),
            "minSheet" => json_decode($minSheet->toJson(), true),
            "pressSheet" => json_decode($pressSheet->toJson(), true),

        ];

        foreach ($this->getTiles() as $tile) {
            $array["tiles"][] = [
                "mmPositions" => json_decode($tile->getInnerTile()->toJson(), true),
                "mmCutBufferPositions" => json_decode($tile->getTileWithSpacing()->toJson(), true)
            ];
        }

        return $array;

    }
}