<?php

namespace App\Tests\Unit\Application\Process\Service;

use App\Application\Process\Service\ActionPathRanker;
use App\Tests\Unit\Application\Process\ProcessTestBase;

/**
 * Unit tests for ActionPathRanker.
 *
 * Tests the path selection and cost calculation logic.
 */
class ActionPathRankerTest extends ProcessTestBase
{
    private ActionPathRanker $ranker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ranker = new ActionPathRanker();
    }

    /**
     * @test
     */
    public function selectBest_returns_top_n_paths_by_cost(): void
    {
        $paths = [
            $this->createPathWithCost(300),
            $this->createPathWithCost(100),
            $this->createPathWithCost(200),
        ];

        $result = $this->ranker->selectBest($paths, 2);

        $this->assertCount(2, $result);
        // First should be lowest cost (100)
        $this->assertEquals(100, $this->ranker->calculateCost($result[0]));
        // Second should be next lowest (200)
        $this->assertEquals(200, $this->ranker->calculateCost($result[1]));
    }

    /**
     * @test
     */
    public function selectBest_handles_empty_input(): void
    {
        $result = $this->ranker->selectBest([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function selectBest_respects_limit_parameter(): void
    {
        $paths = [];
        for ($i = 0; $i < 5; $i++) {
            $paths[] = $this->createPathWithCost($i * 100);
        }

        $result = $this->ranker->selectBest($paths, 3);

        $this->assertCount(3, $result);
    }

    /**
     * @test
     */
    public function selectBest_filters_duplicate_costs(): void
    {
        $paths = [
            $this->createPathWithCost(100),
            $this->createPathWithCost(100), // duplicate
            $this->createPathWithCost(200),
            $this->createPathWithCost(200), // duplicate
        ];

        $result = $this->ranker->selectBest($paths, 10);

        // Only 2 unique costs
        $this->assertCount(2, $result);
    }

    /**
     * @test
     */
    public function selectBest_uses_default_limit_of_10(): void
    {
        $paths = [];
        for ($i = 0; $i < 20; $i++) {
            $paths[] = $this->createPathWithCost($i * 10);
        }

        $result = $this->ranker->selectBest($paths);

        $this->assertCount(10, $result);
    }

    /**
     * @test
     */
    public function selectBest_sorts_ascending_by_cost(): void
    {
        $paths = [
            $this->createPathWithCost(500),
            $this->createPathWithCost(100),
            $this->createPathWithCost(300),
            $this->createPathWithCost(200),
            $this->createPathWithCost(400),
        ];

        $result = $this->ranker->selectBest($paths, 5);

        $costs = array_map(fn($path) => $this->ranker->calculateCost($path), $result);
        $this->assertEquals([100, 200, 300, 400, 500], $costs);
    }

    /**
     * @test
     */
    public function calculateCost_sums_node_costs(): void
    {
        $path = [
            $this->createNodeWithCost(50),
            $this->createNodeWithCost(30),
            $this->createNodeWithCost(20),
        ];

        $cost = $this->ranker->calculateCost($path);

        $this->assertEquals(100, $cost);
    }

    /**
     * @test
     */
    public function calculateCost_handles_array_cost_format(): void
    {
        $node = $this->createNodeWithArrayCost(['cost' => 100, 'paper' => 20, 'ink' => 10]);

        $cost = $this->ranker->calculateCost([$node]);

        // Should only use 'cost' key value
        $this->assertEquals(100, $cost);
    }

    /**
     * @test
     */
    public function calculateCost_returns_zero_for_missing_cost(): void
    {
        $node = $this->createMock(\App\Domain\Action\Interfaces\ActionPathNodeInterface::class);
        $node->method('getTodo')->willReturn([]);

        $cost = $this->ranker->calculateCost([$node]);

        $this->assertEquals(0, $cost);
    }

    /**
     * @test
     */
    public function calculateCost_returns_zero_for_empty_path(): void
    {
        $cost = $this->ranker->calculateCost([]);

        $this->assertEquals(0, $cost);
    }
}
