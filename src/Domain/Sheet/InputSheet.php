<?php

namespace App\Domain\Sheet;

use App\Domain\Geometry\AlignmentMode;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Direction;
use App\Domain\Geometry\GeometryFactory;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Geometry\Interfaces\PositionInterface;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Geometry\RectangleId;
use App\Domain\Sheet\Interfaces\InputSheetInterface;

class InputSheet extends Sheet implements InputSheetInterface
{
    protected ?RectangleInterface $gripMargin;
    protected GripMarginPosition $gripMarginPosition;

    protected string $viewModelClass = InputSheetView::class;

    protected string $contentType;

    public function __construct(
        PositionInterface $position,
        DimensionsInterface $dimensions,
        GeometryFactory $geometryFactory,
        ?RectangleId $id,
    )
    {
        parent::__construct($position, $dimensions, $geometryFactory, $id);
        $this->gripMargin = $this->geometryFactory->newRectangle("gripMargin", 0, 0, 0, 0);
        $this->gripMargin->placeOnto($this, $this->geometryFactory->newPosition(0, 0));
    }

    public function setGripMarginSize(float $size): static
    {
        if ($this->getWidth() >= $this->getHeight()) {
            return $this->setHorizontalGripMargin($size);
        }

        return $this->setVerticalGripMargin($size);
    }

    public function getGripMarginSize(): float
    {
        if ($this->getGripMarginPosition() === GripMarginPosition::Top) {
            return $this->gripMargin->getHeight();
        }

        return $this->gripMargin->getWidth();
    }

    protected function setHorizontalGripMargin(float $size): static
    {
        $this->gripMargin
            ->setDimensions(new Dimensions($this->getWidth(), $size))
            ->alignTo($this, AlignmentMode::TopCenterToTopCenter)
        ;

        $this->usableArea->resize(
            new Dimensions(
                $this->getWidth(),
                $this->getHeight() - $this->gripMargin->getHeight()
            ),
            Direction::TopCenter
        );
        $this->usableArea->alignTo($this->gripMargin, AlignmentMode::TopCenterToBottomCenter);

        $this->setGripMarginPosition(GripMarginPosition::Top);

        return $this;
    }

    protected function setVerticalGripMargin(float $size): static
    {
        $this->gripMargin
            ->setDimensions(new Dimensions($size, $this->getHeight()))
            ->alignTo($this, AlignmentMode::MiddleLeftToMiddleLeft)
        ;

        $this->usableArea->resize(
            new Dimensions(
                $this->getWidth() - $this->gripMargin->getWidth(),
                $this->getHeight(),
            ),
            Direction::MiddleLeft
        );
        $this->usableArea->alignTo($this->gripMargin, AlignmentMode::MiddleLeftToMiddleRight);

        $this->setGripMarginPosition(GripMarginPosition::Left);

        return $this;
    }

    public function getGripMarginPosition(): GripMarginPosition
    {
        return $this->gripMarginPosition;
    }

    protected function setGripMarginPosition(GripMarginPosition $gripMarginPosition): static
    {
        $this->gripMarginPosition = $gripMarginPosition;
        return $this;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): InputSheet
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function resize(DimensionsInterface $newDimensions, Direction $direction): \App\Domain\Geometry\PositionedRectangle
    {
        parent::resize($newDimensions, $direction);
        return $this->setGripMarginSize($this->getGripMarginSize());
    }

}