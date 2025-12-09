<?php

namespace App\Domain\Action\Interfaces;

/**
 * Interface for action tree nodes.
 *
 * An ActionTreeNode extends ActionPathNode by adding tree structure
 * (previous actions), enabling the building of production planning trees.
 */
interface ActionTreeNodeInterface extends ActionPathNodeInterface
{
    /**
     * Get the previous actions (child nodes in the tree).
     *
     * @return ActionTreeNodeInterface[] Previous action nodes
     */
    public function getPrevActions(): array;

    /**
     * Set the previous actions (child nodes in the tree).
     *
     * @param ActionTreeNodeInterface[] $prevActions Previous action nodes
     * @return static
     */
    public function setPrevActions(array $prevActions): static;
}