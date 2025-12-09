<?php

namespace App\Domain\Action\Interfaces;

use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

/**
 * Interface for the action tree builder.
 *
 * The ActionTree is responsible for:
 * 1. Building a tree of possible machine assignments (calculateTree/calculate)
 * 2. Flattening the tree into linear action paths (flattenTree/flatten)
 * 3. Extending paths with additional actions like CTP, cutting (extend)
 *
 * Action paths are returned in "backtrace order" - the last production step first.
 */
interface ActionTreeInterface
{
    /**
     * Get the root nodes of the action tree.
     *
     * @return ActionTreeNodeInterface[]
     */
    public function getRoot(): array;

    /**
     * Set the root nodes of the action tree.
     *
     * @param ActionTreeNodeInterface[] $root
     * @return static
     */
    public function setRoot(array $root): static;

    /**
     * Get the open pose dimensions.
     */
    public function getOpenPoseDimensions(): DimensionsInterface;

    /**
     * Set the open pose dimensions.
     *
     * @return static
     */
    public function setOpenPoseDimensions(DimensionsInterface $openPoseDimensions): static;

    /**
     * Get the closed pose dimensions.
     */
    public function getClosedPoseDimensions(): DimensionsInterface;

    /**
     * Set the closed pose dimensions.
     *
     * @return static
     */
    public function setClosedPoseDimensions(DimensionsInterface $closedPoseDimensions): static;

    /**
     * Get the number of copies to produce.
     */
    public function getNumberOfCopies(): float;

    /**
     * Set the number of copies to produce.
     *
     * @return static
     */
    public function setNumberOfCopies(float $numberOfCopies): static;

    /**
     * Get the number of colors.
     */
    public function getNumberOfColors(): float;

    /**
     * Set the number of colors.
     *
     * @return static
     */
    public function setNumberOfColors(float $numberOfColors): static;

    /**
     * Get the paper weight in g/m².
     */
    public function getPaperWeight(): float;

    /**
     * Set the paper weight in g/m².
     *
     * @return static
     */
    public function setPaperWeight(float $paperWeight): static;

    /**
     * Get the inking specification.
     *
     * @return array Inking specification ['recto' => [...], 'verso' => [...]]
     */
    public function getInking(): array;

    /**
     * Set the inking specification.
     *
     * @param array $inking Inking specification ['recto' => [...], 'verso' => [...]]
     * @return static
     */
    public function setInking(array $inking): static;

    /**
     * Build the action tree for a given set of abstract actions.
     *
     * @param AbstractActionInterface[] $abstractActions Actions to process
     * @param PressSheetInterface $pressSheet The press sheet to use
     * @param InputSheetInterface $zone The zone/input sheet
     * @param array $prevNodes Previous nodes (unused, kept for compatibility)
     * @param array $inking Inking specification ['recto' => [...], 'verso' => [...]]
     * @return ActionTreeNodeInterface[] Root nodes of the built tree
     */
    public function calculateTree(
        array $abstractActions,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        array $prevNodes = [],
        array $inking = [],
    ): array;

    /**
     * Flatten the action tree into linear action paths.
     *
     * Converts the tree structure into an array of paths, where each path
     * is a linear sequence of actions. Paths are returned in "backtrace order"
     * (last production step first).
     *
     * @return ActionTreeNodeInterface[][] Array of action paths
     */
    public function flattenTree(): array;

    /**
     * Process abstract actions and return extended action paths.
     *
     * This is the main entry point for production planning. It:
     * 1. Sets up internal state from parameters
     * 2. For each press sheet: builds tree, flattens, extends
     * 3. Returns all extended action paths
     *
     * @param AbstractActionInterface[] $abstractActions Actions to process
     * @param PressSheetInterface[] $pressSheets Available press sheets
     * @param InputSheetInterface $zone The zone/input sheet
     * @param DimensionsInterface $openPoseDimensions Open pose dimensions
     * @param DimensionsInterface $closedPoseDimensions Closed pose dimensions
     * @param float $numberOfCopies Number of copies to produce
     * @param float $numberOfColors Number of colors
     * @param float $paperWeight Paper weight in g/m²
     * @param array $inking Inking specification
     * @return ActionPathNodeInterface[][] Extended action paths
     */
    public function process(
        array $abstractActions,
        array $pressSheets,
        InputSheetInterface $zone,
        DimensionsInterface $openPoseDimensions,
        DimensionsInterface $closedPoseDimensions,
        float $numberOfCopies,
        float $numberOfColors,
        float $paperWeight,
        array $inking,
    ): array;

    /**
     * Extend a flat action path with additional actions (CTP, cutting, verso).
     *
     * @param ActionTreeNodeInterface[] $flatActionPath The flattened action path
     * @return ActionPathNodeInterface[] Extended action path with inserted actions
     */
    public function extend(array $flatActionPath): array;
}