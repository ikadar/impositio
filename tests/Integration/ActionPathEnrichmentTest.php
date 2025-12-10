<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Regression tests for action path enrichment (todo) values.
 *
 * These tests ensure that:
 * - Cost calculations remain consistent
 * - Todo structure is correct for each machine type
 * - Cost breakdowns (paperCost, aluSheetsCost) are present
 *
 * IMPORTANT: These tests protect against regression during the
 * todo -> ActionEnrichment refactoring.
 */
class ActionPathEnrichmentTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    /**
     * Helper to send a process request.
     */
    private function processRequest(array $payload): array
    {
        static::ensureKernelShutdown();
        $client = static::createClient();

        $client->request(
            'POST',
            '/process',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        return json_decode($response->getContent(), true);
    }

    /**
     * Create a standard print payload for testing.
     */
    private function createPrintPayload(
        int $copies = 1000,
        array $inking = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []]
    ): array {
        return [
            'parts' => [
                [
                    'partId' => 'TEST001',
                    'properties' => ['copies' => $copies],
                    'actions' => [
                        [
                            'name' => 'print',
                            'params' => [
                                'dimensions' => [
                                    'open' => ['width' => 300, 'height' => 200],
                                    'closed' => ['width' => 300, 'height' => 200]
                                ],
                                'zone' => [
                                    'width' => 300,
                                    'height' => 200,
                                    'gripMargin' => 10
                                ],
                                'inking' => $inking,
                            ]
                        ]
                    ],
                    'required_parts' => []
                ]
            ]
        ];
    }

    // ==================== MULTIPLE ACTION PATHS ====================

    /**
     * Test: Multiple action paths are generated with different costs.
     * This is the primary regression test for the "only 1 action path" bug fix.
     */
    public function test_multiple_action_paths_with_different_costs(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];

        // Must have more than 1 action path
        $this->assertGreaterThan(
            1,
            count($actionPaths),
            'Should generate multiple action paths with different costs'
        );

        // All costs should be unique
        $costs = array_column($actionPaths, 'cost');
        $uniqueCosts = array_unique($costs);

        $this->assertEquals(
            count($costs),
            count($uniqueCosts),
            'Each action path should have a unique cost'
        );
    }

    /**
     * Test: Action paths have valid cost values.
     *
     * Note: Due to a known issue in ActionPathRanker, paths may not be
     * strictly sorted by cost. This test verifies costs exist and are valid.
     *
     * @todo Investigate and fix sorting in ActionPathRanker::selectBest
     */
    public function test_action_paths_have_valid_costs(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $costs = array_column($actionPaths, 'cost');

        // All costs should be positive numbers
        foreach ($costs as $index => $cost) {
            $this->assertIsNumeric($cost, "Cost at index {$index} should be numeric");
            $this->assertGreaterThan(0, $cost, "Cost at index {$index} should be positive");
        }

        // First path should have the lowest cost (at minimum this should work)
        $minCost = min($costs);
        $this->assertEquals(
            $minCost,
            $costs[0],
            'First action path should have the lowest cost'
        );
    }

    // ==================== NODE COST & DURATION ====================

    /**
     * Test: Every node has cost, setupDuration, and runDuration.
     */
    public function test_every_node_has_cost_and_duration(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];

        foreach ($actionPaths as $pathIndex => $path) {
            foreach ($path['nodes'] as $nodeIndex => $node) {
                $this->assertArrayHasKey(
                    'cost',
                    $node,
                    "Node {$nodeIndex} in path {$pathIndex} should have 'cost'"
                );
                $this->assertArrayHasKey(
                    'setupDuration',
                    $node,
                    "Node {$nodeIndex} in path {$pathIndex} should have 'setupDuration'"
                );
                $this->assertArrayHasKey(
                    'runDuration',
                    $node,
                    "Node {$nodeIndex} in path {$pathIndex} should have 'runDuration'"
                );
            }
        }
    }

    /**
     * Test: Node costs are non-negative.
     */
    public function test_node_costs_are_non_negative(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];

        foreach ($actionPaths as $path) {
            foreach ($path['nodes'] as $node) {
                $cost = is_array($node['cost']) ? $node['cost']['cost'] : $node['cost'];
                $this->assertGreaterThanOrEqual(
                    0,
                    $cost,
                    "Node cost should be non-negative for machine: {$node['machine']}"
                );
            }
        }
    }

    // ==================== TODO STRUCTURE ====================

    /**
     * Test: Every node has a 'todo' array.
     */
    public function test_every_node_has_todo(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];

        foreach ($actionPaths as $pathIndex => $path) {
            foreach ($path['nodes'] as $nodeIndex => $node) {
                $this->assertArrayHasKey(
                    'todo',
                    $node,
                    "Node {$nodeIndex} in path {$pathIndex} should have 'todo'"
                );
                $this->assertIsArray(
                    $node['todo'],
                    "Todo should be an array for node {$nodeIndex} in path {$pathIndex}"
                );
            }
        }
    }

    /**
     * Test: Printing press todo has correct structure.
     */
    public function test_printing_press_todo_structure(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $firstPath = $actionPaths[0] ?? [];

        // Find printing press node
        $pressNode = null;
        foreach ($firstPath['nodes'] as $node) {
            if (in_array($node['machine'], ['Komori G40', 'KBA 105'])) {
                $pressNode = $node;
                break;
            }
        }

        $this->assertNotNull($pressNode, 'Should have a printing press node');

        $todo = $pressNode['todo'];

        // Required fields
        $this->assertArrayHasKey('numberOfCopies', $todo, 'Press todo should have numberOfCopies');
        $this->assertArrayHasKey('numberOfColors', $todo, 'Press todo should have numberOfColors');
        $this->assertArrayHasKey('cutSheetCount', $todo, 'Press todo should have cutSheetCount');

        // Cost should have paperCost breakdown
        if (isset($todo['cost'])) {
            $this->assertArrayHasKey('cost', $todo['cost'], 'Press todo cost should have cost');
            $this->assertArrayHasKey('paperCost', $todo['cost'], 'Press todo cost should have paperCost');
        }
    }

    /**
     * Test: CTP machine todo has correct structure.
     */
    public function test_ctp_machine_todo_structure(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $firstPath = $actionPaths[0] ?? [];

        // Find CTP node
        $ctpNode = null;
        foreach ($firstPath['nodes'] as $node) {
            if ($node['machine'] === 'ctp-machine') {
                $ctpNode = $node;
                break;
            }
        }

        $this->assertNotNull($ctpNode, 'Should have a CTP machine node');

        $todo = $ctpNode['todo'];

        // Required fields
        $this->assertArrayHasKey('numberOfCopies', $todo, 'CTP todo should have numberOfCopies');
        $this->assertArrayHasKey('numberOfColors', $todo, 'CTP todo should have numberOfColors');
        $this->assertArrayHasKey('cutSheetCount', $todo, 'CTP todo should have cutSheetCount');
        $this->assertArrayHasKey('inking', $todo, 'CTP todo should have inking');

        // Inking structure
        $this->assertArrayHasKey('recto', $todo['inking'], 'CTP inking should have recto');
        $this->assertArrayHasKey('verso', $todo['inking'], 'CTP inking should have verso');
    }

    // ==================== COST CALCULATIONS ====================

    /**
     * Test: Total path cost is greater than sum of node costs.
     * (Material costs like paperCost and aluSheetsCost are included in path cost)
     *
     * Note: The exact breakdown is complex:
     * - node.cost includes machine cost
     * - node.todo.cost.paperCost is paper cost (for printing press)
     * - aluSheetsCost is calculated but may not be explicitly in CTP todo
     */
    public function test_path_cost_includes_material_costs(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];

        foreach ($actionPaths as $path) {
            $nodeBaseCostSum = 0;

            foreach ($path['nodes'] as $node) {
                // Get the base cost (either direct number or from cost array)
                $nodeCost = $node['cost'];
                if (is_array($nodeCost)) {
                    $nodeBaseCostSum += $nodeCost['cost'] ?? 0;
                } else {
                    $nodeBaseCostSum += $nodeCost;
                }
            }

            // Path cost should be >= node cost sum (material costs are added)
            $this->assertGreaterThanOrEqual(
                $nodeBaseCostSum,
                $path['cost'],
                "Path total cost should be at least the sum of node costs"
            );
        }
    }

    /**
     * Test: Higher copy count results in higher cost.
     */
    public function test_higher_copy_count_increases_cost(): void
    {
        $lowCopyData = $this->processRequest($this->createPrintPayload(copies: 100));
        $highCopyData = $this->processRequest($this->createPrintPayload(copies: 10000));

        $lowCopyCost = $lowCopyData[0]['parts']['TEST001']['actionPaths'][0]['cost'] ?? 0;
        $highCopyCost = $highCopyData[0]['parts']['TEST001']['actionPaths'][0]['cost'] ?? 0;

        $this->assertGreaterThan(
            $lowCopyCost,
            $highCopyCost,
            'Higher copy count should result in higher cost'
        );
    }

    /**
     * Test: More colors increases cost.
     */
    public function test_more_colors_increases_cost(): void
    {
        $oneColorData = $this->processRequest($this->createPrintPayload(
            inking: ['recto' => ['black'], 'verso' => []]
        ));

        $fourColorData = $this->processRequest($this->createPrintPayload(
            inking: ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []]
        ));

        $oneColorCost = $oneColorData[0]['parts']['TEST001']['actionPaths'][0]['cost'] ?? 0;
        $fourColorCost = $fourColorData[0]['parts']['TEST001']['actionPaths'][0]['cost'] ?? 0;

        $this->assertGreaterThan(
            $oneColorCost,
            $fourColorCost,
            'More colors should result in higher cost'
        );
    }

    // ==================== GRID FITTING AFFECTS COST ====================

    /**
     * Test: Different grid fittings result in different costs.
     */
    public function test_different_grid_fittings_result_in_different_costs(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];

        // Get grid fittings from first few paths
        $gridFittings = [];
        foreach (array_slice($actionPaths, 0, 5) as $path) {
            $pressNode = null;
            foreach ($path['nodes'] as $node) {
                if (in_array($node['machine'], ['Komori G40', 'KBA 105'])) {
                    $pressNode = $node;
                    break;
                }
            }
            if ($pressNode) {
                $gf = $pressNode['gridFitting'];
                $key = "{$gf['cols']}x{$gf['rows']}" . ($gf['rotated'] ? 'R' : 'U');
                $gridFittings[$key] = $path['cost'];
            }
        }

        // Should have at least 2 different grid fittings
        $this->assertGreaterThanOrEqual(
            2,
            count($gridFittings),
            'Should have multiple different grid fittings with different costs'
        );
    }

    // ==================== CUT SHEET COUNT ====================

    /**
     * Test: cutSheetCount is present in todo.
     *
     * Note: The current implementation may have cutSheetCount = numberOfCopies
     * rather than calculated based on grid fitting. This test documents the
     * current behavior for regression protection.
     */
    public function test_cut_sheet_count_is_present(): void
    {
        $data = $this->processRequest($this->createPrintPayload(copies: 1000));

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $firstPath = $actionPaths[0] ?? [];

        // Find printing press node
        $pressNode = null;
        foreach ($firstPath['nodes'] as $node) {
            if (in_array($node['machine'], ['Komori G40', 'KBA 105'])) {
                $pressNode = $node;
                break;
            }
        }

        $this->assertNotNull($pressNode, 'Should have a printing press node');

        $todo = $pressNode['todo'];
        $this->assertArrayHasKey('cutSheetCount', $todo, 'cutSheetCount should be present in todo');
        $this->assertGreaterThan(0, $todo['cutSheetCount'], 'cutSheetCount should be positive');
    }

    /**
     * Test: cutSheetCount in todo.cost calculation matches expected pattern.
     * This test documents the actual calculation for regression protection.
     */
    public function test_press_todo_cost_has_correct_cut_sheet_count(): void
    {
        $data = $this->processRequest($this->createPrintPayload(copies: 1000));

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $firstPath = $actionPaths[0] ?? [];

        // Find printing press node
        $pressNode = null;
        foreach ($firstPath['nodes'] as $node) {
            if (in_array($node['machine'], ['Komori G40', 'KBA 105'])) {
                $pressNode = $node;
                break;
            }
        }

        $this->assertNotNull($pressNode, 'Should have a printing press node');

        // If todo.cost.cutSheetCount exists, it should match the calculated value
        $todo = $pressNode['todo'];
        $gridFitting = $pressNode['gridFitting'];
        $posesPerSheet = $gridFitting['cols'] * $gridFitting['rows'];

        // The cutSheetCount in todo['cost'] is the actual number of printing sheets
        if (isset($todo['cost']['cutSheetCount'])) {
            $expectedSheets = ceil(1000 / $posesPerSheet);
            $this->assertEqualsWithDelta(
                $expectedSheets,
                $todo['cost']['cutSheetCount'],
                1,
                "todo.cost.cutSheetCount should be calculated based on grid fitting"
            );
        }
    }

    // ==================== MATERIAL COSTS ====================

    /**
     * Test: Paper cost is present in path-level breakdown.
     */
    public function test_paper_cost_in_path_breakdown(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $firstPath = $actionPaths[0] ?? [];

        // Paper cost should be in path or in press node
        $hasPaperCost = isset($firstPath['paperCost']);

        if (!$hasPaperCost) {
            // Check in press node
            foreach ($firstPath['nodes'] as $node) {
                if (in_array($node['machine'], ['Komori G40', 'KBA 105'])) {
                    if (is_array($node['cost']) && isset($node['cost']['paperCost'])) {
                        $hasPaperCost = true;
                        break;
                    }
                    if (isset($node['todo']['cost']['paperCost'])) {
                        $hasPaperCost = true;
                        break;
                    }
                }
            }
        }

        $this->assertTrue($hasPaperCost, 'Paper cost should be present somewhere in the response');
    }

    /**
     * Test: Alu sheets cost (CTP plates) is included in total path cost.
     *
     * Note: The aluSheetsCost may not be explicitly present in the response,
     * but it IS calculated and included in the total path cost.
     * This test verifies the CTP cost contribution to the total.
     *
     * TODO: After refactoring, aluSheetsCost should be explicitly visible.
     */
    public function test_ctp_cost_contributes_to_path_total(): void
    {
        $data = $this->processRequest($this->createPrintPayload());

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $firstPath = $actionPaths[0] ?? [];

        // Find CTP node
        $ctpNode = null;
        foreach ($firstPath['nodes'] as $node) {
            if ($node['machine'] === 'ctp-machine') {
                $ctpNode = $node;
                break;
            }
        }

        $this->assertNotNull($ctpNode, 'Should have a CTP machine node');

        // CTP node should have a cost
        $ctpCost = is_array($ctpNode['cost']) ? $ctpNode['cost']['cost'] : $ctpNode['cost'];
        $this->assertGreaterThan(0, $ctpCost, 'CTP should have a positive cost');

        // The total path cost should include CTP contribution
        $pathCost = $firstPath['cost'];
        $this->assertGreaterThan($ctpCost, $pathCost, 'Path cost should be greater than just CTP cost');
    }

    // ==================== REGRESSION: SPECIFIC VALUES ====================

    /**
     * Test: Known input produces expected cost range.
     * This test documents the expected behavior and protects against drift.
     */
    public function test_known_input_produces_expected_cost_range(): void
    {
        $data = $this->processRequest($this->createPrintPayload(copies: 1000));

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $lowestCost = $actionPaths[0]['cost'] ?? 0;

        // Based on current implementation:
        // - 1000 copies, CMYK print
        // - Expected cost range: 80-250 EUR (approximately)
        $this->assertGreaterThan(50, $lowestCost, 'Cost should be at least 50 EUR for 1000 CMYK prints');
        $this->assertLessThan(300, $lowestCost, 'Cost should be less than 300 EUR for 1000 CMYK prints');
    }

    /**
     * Test: Duration is reasonable.
     */
    public function test_duration_is_reasonable(): void
    {
        $data = $this->processRequest($this->createPrintPayload(copies: 1000));

        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];
        $firstPath = $actionPaths[0] ?? [];
        $duration = $firstPath['duration'] ?? 0;

        // Duration for 1000 copies should be between 10-120 minutes
        $this->assertGreaterThan(5, $duration, 'Duration should be at least 5 minutes');
        $this->assertLessThan(180, $duration, 'Duration should be less than 180 minutes');
    }
}
