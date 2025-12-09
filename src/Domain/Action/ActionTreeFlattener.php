<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\ActionTreeNodeInterface;

/**
 * Flattens action trees into linear action paths.
 *
 * Responsible for:
 * - Converting tree structures into arrays of paths
 * - Each path is a linear sequence of actions
 * - Paths are returned in "backtrace order" (last production step first)
 */
class ActionTreeFlattener
{
    /**
     * Flatten the action tree into linear action paths.
     *
     * Converts the tree structure into an array of paths, where each path
     * is a linear sequence of actions. Paths are returned in "backtrace order"
     * (last production step first).
     *
     * @param ActionTreeNodeInterface[] $rootNodes Root nodes of the tree
     * @return ActionTreeNodeInterface[][] Array of action paths
     */
    public function flattenTree(array $rootNodes): array
    {
        $forwardPaths = [];
        foreach ($rootNodes as $rootNode) {
            $forwardPaths = array_merge($forwardPaths, $this->flattenNode($rootNode, []));
        }

        // Reverse paths to get backtrace order
        $backtracePaths = [];
        foreach ($forwardPaths as $forwardPath) {
            $backtracePaths[] = array_reverse($forwardPath);
        }

        return $backtracePaths;
    }

    /**
     * Flatten a single node into forward-order paths.
     *
     * This method is exposed for backward compatibility with ActionTree::flatten().
     *
     * @param ActionTreeNodeInterface $node Node to flatten
     * @param array $path Current path being built
     * @return ActionTreeNodeInterface[][] Forward-order paths
     */
    public function flatten(ActionTreeNodeInterface $node, array $path = []): array
    {
        return $this->flattenNode($node, $path);
    }

    /**
     * Recursively flatten a tree node into paths.
     *
     * Traverses from root to leaves, collecting all possible paths.
     * Each node is cloned to prevent modification of the original tree.
     *
     * @param ActionTreeNodeInterface $node Current node to process
     * @param ActionTreeNodeInterface[] $path Current path being built
     * @return ActionTreeNodeInterface[][] All paths from this node to leaves
     */
    private function flattenNode(ActionTreeNodeInterface $node, array $path = []): array
    {
        $current = clone $node;
        $current->setPrevActions([]);
        $path[] = $current;

        if (empty($node->getPrevActions())) {
            return [$path];
        }

        $result = [];
        foreach ($node->getPrevActions() as $prevNode) {
            $subPaths = $this->flatten($prevNode, $path);
            foreach ($subPaths as $subPath) {
                $result[] = $subPath;
            }
        }

        return $result;
    }
}
