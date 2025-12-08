<?php

namespace App\Domain\Equipment\Interfaces;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Equipment\TodoContext;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;

interface MachineInterface
{
    public function getId(): string;
    public function getGripMarginSize(): float;
    public function getMinSheetDimensions(): Dimensions;
    public function getMaxSheetDimensions(): Dimensions;
    public function getMinSheetRectangle(): RectangleInterface;
    public function getMaxSheetRectangle(): RectangleInterface;
    public function setOpenPoseDimensions(Dimensions $dimensions): void;
    public function calculateCost(ActionPathNodeInterface $action): float | array;
    public function getMaxPoseCount(): ?int;
    public function getType(): \App\Domain\Equipment\MachineType;

    /**
     * Prepare the todo array for this machine based on the given context.
     * Each machine type knows what parameters it needs for cost/duration calculation.
     */
    public function prepareTodo(TodoContext $context): array;
}