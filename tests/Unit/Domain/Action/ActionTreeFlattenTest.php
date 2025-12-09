<?php

namespace App\Tests\Unit\Domain\Action;

use App\Domain\Action\ActionTreeNode;
use App\Domain\Equipment\MachineType;

/**
 * Unit tests for ActionTree::flatten() and flattenTree() methods.
 * Tests the tree flattening logic that converts tree structure to paths.
 */
class ActionTreeFlattenTest extends ActionTreeTestBase
{
    /**
     * Test: flatten() with leaf node (no prevActions).
     * Expected: Single path containing only the leaf node.
     */
    public function test_flatten_leaf_node(): void
    {
        $leafNode = $this->createActionTreeNode(
            $this->createMachineMock('leaf', MachineType::CuttingMachine)
        );

        $actionTree = $this->createConfiguredActionTree();

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('flatten');
        $method->setAccessible(true);

        $result = $method->invoke($actionTree, $leafNode, []);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]);
        $this->assertEquals('leaf', $result[0][0]->getMachine()->getId());
    }

    /**
     * Test: flatten() with single level tree (root with children but no grandchildren).
     * Expected: One path per child: [[Root, Child1], [Root, Child2]].
     */
    public function test_flatten_single_level_tree(): void
    {
        $child1 = $this->createActionTreeNode(
            $this->createMachineMock('child1', MachineType::PrintingPress)
        );
        $child2 = $this->createActionTreeNode(
            $this->createMachineMock('child2', MachineType::PrintingPress)
        );

        $root = $this->createActionTreeNode(
            $this->createMachineMock('root', MachineType::CTPMachine),
            null,
            [$child1, $child2]
        );

        $actionTree = $this->createConfiguredActionTree();

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('flatten');
        $method->setAccessible(true);

        $result = $method->invoke($actionTree, $root, []);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        // Each path should have 2 nodes (root + child)
        foreach ($result as $path) {
            $this->assertCount(2, $path);
            $this->assertEquals('root', $path[0]->getMachine()->getId());
        }

        // Paths should lead to different children
        $pathEnds = array_map(fn($path) => $path[1]->getMachine()->getId(), $result);
        $this->assertContains('child1', $pathEnds);
        $this->assertContains('child2', $pathEnds);
    }

    /**
     * Test: flatten() with deep tree structure (multiple levels).
     * Expected: Single path with all nodes in order.
     */
    public function test_flatten_deep_tree(): void
    {
        // Create a deep tree: Root → Child → Grandchild → Leaf
        $leaf = $this->createActionTreeNode(
            $this->createMachineMock('leaf', MachineType::CuttingMachine)
        );
        $grandchild = $this->createActionTreeNode(
            $this->createMachineMock('grandchild', MachineType::PrintingPress),
            null,
            [$leaf]
        );
        $child = $this->createActionTreeNode(
            $this->createMachineMock('child', MachineType::PrintingPress),
            null,
            [$grandchild]
        );
        $root = $this->createActionTreeNode(
            $this->createMachineMock('root', MachineType::CTPMachine),
            null,
            [$child]
        );

        $actionTree = $this->createConfiguredActionTree();

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('flatten');
        $method->setAccessible(true);

        $result = $method->invoke($actionTree, $root, []);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertCount(4, $result[0]);

        // Nodes should be in forward order: root, child, grandchild, leaf
        $machineIds = array_map(fn($node) => $node->getMachine()->getId(), $result[0]);
        $this->assertEquals(['root', 'child', 'grandchild', 'leaf'], $machineIds);
    }

    /**
     * Test: flatten() with complex branching tree.
     * Structure:
     *     Root
     *    /    \
     * Child1  Child2
     *   |       |
     * Leaf1   Leaf2
     *
     * Expected: 2 paths with all branches covered.
     */
    public function test_flatten_complex_branching_tree(): void
    {
        $leaf1 = $this->createActionTreeNode(
            $this->createMachineMock('leaf1', MachineType::CuttingMachine)
        );
        $leaf2 = $this->createActionTreeNode(
            $this->createMachineMock('leaf2', MachineType::CuttingMachine)
        );
        $child1 = $this->createActionTreeNode(
            $this->createMachineMock('child1', MachineType::PrintingPress),
            null,
            [$leaf1]
        );
        $child2 = $this->createActionTreeNode(
            $this->createMachineMock('child2', MachineType::PrintingPress),
            null,
            [$leaf2]
        );
        $root = $this->createActionTreeNode(
            $this->createMachineMock('root', MachineType::CTPMachine),
            null,
            [$child1, $child2]
        );

        $actionTree = $this->createConfiguredActionTree();

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('flatten');
        $method->setAccessible(true);

        $result = $method->invoke($actionTree, $root, []);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        // Each path should have 3 nodes
        foreach ($result as $path) {
            $this->assertCount(3, $path);
            $this->assertEquals('root', $path[0]->getMachine()->getId());
        }

        // Collect all path endings
        $pathSignatures = [];
        foreach ($result as $path) {
            $pathSignatures[] = array_map(fn($n) => $n->getMachine()->getId(), $path);
        }

        // Should have paths: [root, child1, leaf1] and [root, child2, leaf2]
        $this->assertContains(['root', 'child1', 'leaf1'], $pathSignatures);
        $this->assertContains(['root', 'child2', 'leaf2'], $pathSignatures);
    }

    /**
     * Test: flatten() clones nodes to avoid modifying original tree.
     * The cloned node's prevActions should be cleared.
     */
    public function test_flatten_node_cloning(): void
    {
        $child = $this->createActionTreeNode(
            $this->createMachineMock('child', MachineType::PrintingPress)
        );
        $root = $this->createActionTreeNode(
            $this->createMachineMock('root', MachineType::CTPMachine),
            null,
            [$child]
        );

        $actionTree = $this->createConfiguredActionTree();

        $reflection = new \ReflectionClass($actionTree);
        $method = $reflection->getMethod('flatten');
        $method->setAccessible(true);

        $result = $method->invoke($actionTree, $root, []);

        // Original node should still have its prevActions intact
        $this->assertCount(1, $root->getPrevActions());
        $this->assertEquals('child', $root->getPrevActions()[0]->getMachine()->getId());

        // Flattened nodes should have empty prevActions
        foreach ($result as $path) {
            foreach ($path as $node) {
                $this->assertEmpty($node->getPrevActions(), 'Cloned node should have empty prevActions');
            }
        }
    }

    // ==================== flattenTree() Tests ====================

    /**
     * Test: flattenTree() calls flatten() for each root element.
     */
    public function test_flattenTree_calls_flatten_for_each_root(): void
    {
        $root1 = $this->createActionTreeNode(
            $this->createMachineMock('root1', MachineType::PrintingPress)
        );
        $root2 = $this->createActionTreeNode(
            $this->createMachineMock('root2', MachineType::PrintingPress)
        );

        $actionTree = $this->createConfiguredActionTree();
        $actionTree->setRoot([$root1, $root2]);

        $result = $actionTree->flattenTree();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test: flattenTree() reverses each path (backtrace order).
     * Original flatten order: [root, child, leaf]
     * After reverse: [leaf, child, root]
     */
    public function test_flattenTree_reverses_paths(): void
    {
        // Create tree: Root → Child → Leaf
        $leaf = $this->createActionTreeNode(
            $this->createMachineMock('leaf', MachineType::CuttingMachine)
        );
        $child = $this->createActionTreeNode(
            $this->createMachineMock('child', MachineType::PrintingPress),
            null,
            [$leaf]
        );
        $root = $this->createActionTreeNode(
            $this->createMachineMock('root', MachineType::CTPMachine),
            null,
            [$child]
        );

        $actionTree = $this->createConfiguredActionTree();
        $actionTree->setRoot([$root]);

        $result = $actionTree->flattenTree();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertCount(3, $result[0]);

        // Paths should be reversed (backtrace order: leaf first, root last)
        $machineIds = array_map(fn($node) => $node->getMachine()->getId(), $result[0]);
        $this->assertEquals(['leaf', 'child', 'root'], $machineIds);
    }
}
