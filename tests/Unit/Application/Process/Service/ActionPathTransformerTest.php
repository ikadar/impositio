<?php

namespace App\Tests\Unit\Application\Process\Service;

use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\Service\ActionPathTransformer;
use App\Domain\Geometry\Dimensions;
use App\Tests\Unit\Application\Process\ProcessTestBase;

/**
 * Unit tests for ActionPathTransformer.
 *
 * Tests the response transformation logic.
 */
class ActionPathTransformerTest extends ProcessTestBase
{
    private ActionPathTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new ActionPathTransformer();
    }

    /**
     * @test
     */
    public function toResponseArray_returns_correct_structure(): void
    {
        $result = $this->createActionPathResult();
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('designation', $response);
        $this->assertArrayHasKey('nodes', $response);
        $this->assertArrayHasKey('cost', $response);
        $this->assertArrayHasKey('duration', $response);
        $this->assertArrayHasKey('pressSheet', $response);
        $this->assertArrayHasKey('openPoseDimensions', $response);
        $this->assertArrayHasKey('closedPoseDimensions', $response);
        $this->assertArrayHasKey('requiredParts', $response);
    }

    /**
     * @test
     */
    public function toResponseArray_generates_valid_uuid(): void
    {
        $result = $this->createActionPathResult();
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        // UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $response['id']
        );
    }

    /**
     * @test
     */
    public function toResponseArray_builds_designation_string(): void
    {
        $result = $this->createActionPathResult('1000x700');
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        $this->assertStringContainsString('1000x700', $response['designation']);
        $this->assertStringContainsString('Cost:', $response['designation']);
        $this->assertStringContainsString('Duration:', $response['designation']);
        $this->assertStringContainsString('€', $response['designation']);
        $this->assertStringContainsString('min', $response['designation']);
    }

    /**
     * @test
     */
    public function toResponseArray_formats_dimensions_correctly(): void
    {
        $result = $this->createActionPathResult();
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput(
            openDimensions: new Dimensions(200, 300),
            closedDimensions: new Dimensions(100, 150)
        );

        $response = $this->transformer->toResponseArray($result, $part, $input);

        $this->assertEquals('200x300', $response['openPoseDimensions']);
        $this->assertEquals('100x150', $response['closedPoseDimensions']);
    }

    /**
     * @test
     */
    public function toResponseArray_includes_press_sheet_with_mm_suffix(): void
    {
        $result = $this->createActionPathResult('1000x700');
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        $this->assertEquals('1000x700mm', $response['pressSheet']);
    }

    /**
     * @test
     */
    public function toResponseArray_includes_required_parts(): void
    {
        $result = $this->createActionPathResult();
        $part = $this->createPartPayload(requiredParts: ['PART001', 'PART002']);
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        $this->assertEquals(['PART001', 'PART002'], $response['requiredParts']);
    }

    /**
     * @test
     */
    public function toResponseArray_calculates_cost_from_nodes(): void
    {
        $nodes = [
            $this->createNodeWithCost(50),
            $this->createNodeWithCost(30),
        ];
        $result = new ActionPathResult($nodes, 80, 180, '1000x700');
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        // Cost is calculated from nodes (50 + 30)
        $this->assertEquals(80, $response['cost']);
    }

    /**
     * @test
     */
    public function toResponseArray_calculates_duration_from_nodes(): void
    {
        $nodes = [
            $this->createNodeWithCost(50), // 30 setup + 60 run = 90
            $this->createNodeWithCost(30), // 30 setup + 60 run = 90
        ];
        $result = new ActionPathResult($nodes, 80, 180, '1000x700');
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        // Duration is sum of setupDuration + runDuration for each node
        // Each mock node has 30 setup + 60 run = 90, so 2 nodes = 180
        $this->assertEquals(180, $response['duration']);
    }

    /**
     * @test
     */
    public function toResponseArray_returns_nodes_array(): void
    {
        $nodes = [
            $this->createNodeWithCost(50),
            $this->createNodeWithCost(30),
        ];
        $result = new ActionPathResult($nodes, 80, 180, '1000x700');
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        $this->assertIsArray($response['nodes']);
        $this->assertCount(2, $response['nodes']);
    }

    /**
     * @test
     */
    public function toResponseArray_handles_empty_nodes(): void
    {
        $result = new ActionPathResult([], 0, 0, '1000x700');
        $part = $this->createPartPayload();
        $input = $this->createActionTreeInput();

        $response = $this->transformer->toResponseArray($result, $part, $input);

        $this->assertIsArray($response['nodes']);
        $this->assertEmpty($response['nodes']);
        $this->assertEquals(0, $response['cost']);
        $this->assertEquals(0, $response['duration']);
    }

    /**
     * Create an ActionPathResult for testing.
     */
    private function createActionPathResult(string $pressSheetSize = '1000x700'): ActionPathResult
    {
        $node = $this->createNodeWithCost(100);

        return new ActionPathResult(
            nodes: [$node],
            totalCost: 100,
            totalDuration: 90,
            pressSheetSize: $pressSheetSize
        );
    }
}
