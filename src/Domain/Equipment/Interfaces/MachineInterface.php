<?php

namespace App\Domain\Equipment\Interfaces;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Equipment\Enrichment\ActionEnrichmentInterface;
use App\Domain\Equipment\TodoContext;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Job\JobContext;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

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
     *
     * @deprecated Use calculateEnrichment() instead
     */
    public function prepareTodo(TodoContext $context): array;

    /**
     * Calculate and return the enrichment data for this action.
     *
     * This is the new method that replaces prepareTodo(). It returns a typed
     * enrichment object instead of an array.
     *
     * @param JobContext $jobContext Job-level parameters (copies, colors, weight, etc.)
     * @param GridFittingInterface $gridFitting Grid fitting information
     * @param PressSheetInterface $pressSheet Press sheet being used
     * @param float $cutSheetCount Current cut sheet count
     * @return ActionEnrichmentInterface Machine-specific enrichment data
     */
    public function calculateEnrichment(
        JobContext $jobContext,
        GridFittingInterface $gridFitting,
        PressSheetInterface $pressSheet,
        float $cutSheetCount,
    ): ActionEnrichmentInterface;
}