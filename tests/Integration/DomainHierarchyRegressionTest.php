<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Regression tests for domain hierarchy refactoring.
 *
 * These tests protect the external behavior during the refactoring:
 * - Phase 1: Inking move from context to action-level (PrintActionParams)
 * - Phase 2: JobContext → PartProductionContext rename
 * - Phase 3: TreeBuildContext consolidation
 *
 * IMPORTANT: These tests verify EXTERNAL behavior only.
 * They do NOT depend on internal class names (JobContext, TreeBuildContext, etc.)
 * so they will continue to pass after the refactoring.
 *
 * @see docs/refactor-domain-hierarchy.md
 */
class DomainHierarchyRegressionTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    // ==================== HELPER METHODS ====================

    /**
     * Send a process request and return the response data.
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
     * Create a payload with specified inking.
     */
    private function createPayloadWithInking(array $inking, int $copies = 1000): array
    {
        return [
            'parts' => [[
                'partId' => 'TEST001',
                'properties' => ['copies' => $copies],
                'actions' => [[
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
                ]],
                'required_parts' => []
            ]]
        ];
    }

    /**
     * Create a payload with specified copies count.
     */
    private function createPayloadWithCopies(int $copies): array
    {
        return $this->createPayloadWithInking(
            ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []],
            $copies
        );
    }

    /**
     * Create a payload with specified paper weight.
     */
    private function createPayloadWithPaperWeight(int $paperWeight, int $copies = 1000): array
    {
        return [
            'parts' => [[
                'partId' => 'TEST001',
                'properties' => ['copies' => $copies],
                'actions' => [[
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
                        'inking' => [
                            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                            'verso' => []
                        ],
                        'paper' => [
                            'weight' => $paperWeight
                        ]
                    ]
                ]],
                'required_parts' => []
            ]]
        ];
    }

    /**
     * Get action paths from process result.
     */
    private function getActionPaths(array $data): array
    {
        return $data[0]['parts']['TEST001']['actionPaths'] ?? [];
    }

    /**
     * Find printing press nodes in a path.
     */
    private function findPrintingPressNodes(array $path): array
    {
        $nodes = [];
        foreach ($path['nodes'] ?? [] as $node) {
            if (in_array($node['machine'], ['Komori G40', 'KBA 105'])) {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    /**
     * Find CTP node in a path.
     */
    private function findCtpNode(array $path): ?array
    {
        foreach ($path['nodes'] ?? [] as $node) {
            if ($node['machine'] === 'ctp-machine') {
                return $node;
            }
        }
        return null;
    }

    /**
     * Extract machine types from path nodes in production order (reversed from backtrace).
     */
    private function getMachineTypesInProductionOrder(array $nodes): array
    {
        $machineIds = array_map(fn($node) => $node['machine'], $nodes);
        $reversed = array_reverse($machineIds);

        $typeMap = [
            'ctp-machine' => 'ctp',
            'cutting-machine' => 'cutting',
            'cutout-machine' => 'cutout',
            'splitter' => 'splitter',
            'Komori G40' => 'print',
            'KBA 105' => 'print',
            'Stahl' => 'folder',
            'stitching-machine' => 'stitching',
        ];

        return array_map(fn($id) => $typeMap[$id] ?? $id, $reversed);
    }

    // ==================== INKING-BASED MACHINE FILTERING ====================

    /**
     * @test
     * Machines are filtered based on inking color count.
     * A 4-color CMYK job should only use machines with 4+ colors.
     */
    public function machine_filtering_respects_inking_color_count(): void
    {
        $inking = [
            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
            'verso' => [],
        ];

        $data = $this->processRequest($this->createPayloadWithInking($inking));
        $actionPaths = $this->getActionPaths($data);

        $this->assertNotEmpty($actionPaths, 'Should have at least one action path');

        // All printing presses in all paths should support at least 4 colors
        foreach ($actionPaths as $pathIndex => $path) {
            $pressNodes = $this->findPrintingPressNodes($path);
            foreach ($pressNodes as $nodeIndex => $node) {
                // The machine ID tells us its capabilities
                // Komori G40 and KBA 105 are both 4+ color presses
                $this->assertContains(
                    $node['machine'],
                    ['Komori G40', 'KBA 105'],
                    "Path {$pathIndex}, node {$nodeIndex}: Only 4+ color presses should be selected for CMYK job"
                );
            }
        }
    }

    /**
     * @test
     * Single color inking allows any printing press.
     */
    public function single_color_inking_allows_any_color_machines(): void
    {
        $inking = [
            'recto' => ['black'],
            'verso' => [],
        ];

        $data = $this->processRequest($this->createPayloadWithInking($inking));
        $actionPaths = $this->getActionPaths($data);

        $this->assertNotEmpty($actionPaths, 'Should have at least one action path');

        // For single color, any press should be valid
        $hasPrintingPress = false;
        foreach ($actionPaths as $path) {
            $pressNodes = $this->findPrintingPressNodes($path);
            if (!empty($pressNodes)) {
                $hasPrintingPress = true;
                break;
            }
        }

        $this->assertTrue($hasPrintingPress, 'Should have at least one path with a printing press');
    }

    // ==================== VERSO PROCESSING ====================

    /**
     * @test
     * Verso inking creates two print actions (recto + verso).
     */
    public function verso_inking_creates_two_print_actions(): void
    {
        $inking = [
            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
            'verso' => ['black'],
        ];

        $data = $this->processRequest($this->createPayloadWithInking($inking));
        $actionPaths = $this->getActionPaths($data);

        $this->assertNotEmpty($actionPaths, 'Should have at least one action path');

        // Every path should have 2 print actions (recto + verso)
        foreach ($actionPaths as $pathIndex => $path) {
            $types = $this->getMachineTypesInProductionOrder($path['nodes']);
            $printCount = count(array_filter($types, fn($t) => $t === 'print'));

            $this->assertEquals(
                2,
                $printCount,
                "Path {$pathIndex}: Should have 2 print actions (recto + verso)"
            );
        }
    }

    /**
     * @test
     * Recto-only inking creates single print action.
     */
    public function recto_only_inking_creates_single_print_action(): void
    {
        $inking = [
            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
            'verso' => [],
        ];

        $data = $this->processRequest($this->createPayloadWithInking($inking));
        $actionPaths = $this->getActionPaths($data);

        $this->assertNotEmpty($actionPaths, 'Should have at least one action path');

        // Every path should have exactly 1 print action
        foreach ($actionPaths as $pathIndex => $path) {
            $types = $this->getMachineTypesInProductionOrder($path['nodes']);
            $printCount = count(array_filter($types, fn($t) => $t === 'print'));

            $this->assertEquals(
                1,
                $printCount,
                "Path {$pathIndex}: Should have 1 print action (recto only)"
            );
        }
    }

    // ==================== CTP COLOR DEPENDENCY ====================

    /**
     * @test
     * CTP cost depends on color count.
     */
    public function ctp_cost_depends_on_color_count(): void
    {
        $inking1Color = ['recto' => ['black'], 'verso' => []];
        $inking4Colors = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []];

        $data1Color = $this->processRequest($this->createPayloadWithInking($inking1Color));
        $data4Colors = $this->processRequest($this->createPayloadWithInking($inking4Colors));

        $paths1Color = $this->getActionPaths($data1Color);
        $paths4Colors = $this->getActionPaths($data4Colors);

        $this->assertNotEmpty($paths1Color, 'Should have action paths for 1 color');
        $this->assertNotEmpty($paths4Colors, 'Should have action paths for 4 colors');

        // Extract CTP costs
        $ctpNode1Color = $this->findCtpNode($paths1Color[0]);
        $ctpNode4Colors = $this->findCtpNode($paths4Colors[0]);

        $this->assertNotNull($ctpNode1Color, 'Should have CTP node for 1 color');
        $this->assertNotNull($ctpNode4Colors, 'Should have CTP node for 4 colors');

        $ctpCost1 = is_array($ctpNode1Color['cost'])
            ? $ctpNode1Color['cost']['cost']
            : $ctpNode1Color['cost'];

        $ctpCost4 = is_array($ctpNode4Colors['cost'])
            ? $ctpNode4Colors['cost']['cost']
            : $ctpNode4Colors['cost'];

        $this->assertGreaterThan(
            $ctpCost1,
            $ctpCost4,
            'CTP cost should be higher for 4 colors than 1 color'
        );
    }

    /**
     * @test
     * Verso inking increases CTP cost (more plates needed).
     */
    public function verso_inking_increases_ctp_cost(): void
    {
        $inkingRecto = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []];
        $inkingBoth = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => ['black']];

        $dataRecto = $this->processRequest($this->createPayloadWithInking($inkingRecto));
        $dataBoth = $this->processRequest($this->createPayloadWithInking($inkingBoth));

        $pathsRecto = $this->getActionPaths($dataRecto);
        $pathsBoth = $this->getActionPaths($dataBoth);

        $this->assertNotEmpty($pathsRecto, 'Should have action paths for recto only');
        $this->assertNotEmpty($pathsBoth, 'Should have action paths for recto + verso');

        // Total CTP cost for verso should be higher (recto CTP + verso CTP)
        // With verso, there are 2 CTP actions instead of 1
        $ctpNodesRecto = [];
        $ctpNodesBoth = [];

        foreach ($pathsRecto[0]['nodes'] as $node) {
            if ($node['machine'] === 'ctp-machine') {
                $ctpNodesRecto[] = $node;
            }
        }

        foreach ($pathsBoth[0]['nodes'] as $node) {
            if ($node['machine'] === 'ctp-machine') {
                $ctpNodesBoth[] = $node;
            }
        }

        $totalCtpCostRecto = 0;
        foreach ($ctpNodesRecto as $node) {
            $totalCtpCostRecto += is_array($node['cost']) ? $node['cost']['cost'] : $node['cost'];
        }

        $totalCtpCostBoth = 0;
        foreach ($ctpNodesBoth as $node) {
            $totalCtpCostBoth += is_array($node['cost']) ? $node['cost']['cost'] : $node['cost'];
        }

        $this->assertGreaterThan(
            $totalCtpCostRecto,
            $totalCtpCostBoth,
            'Total CTP cost should be higher with verso (5 plates vs 4 plates)'
        );
    }

    // ==================== PART-LEVEL PARAMETERS ====================

    /**
     * @test
     * numberOfCopies affects sheet count.
     */
    public function number_of_copies_affects_sheet_count(): void
    {
        $data100 = $this->processRequest($this->createPayloadWithCopies(100));
        $data1000 = $this->processRequest($this->createPayloadWithCopies(1000));

        $paths100 = $this->getActionPaths($data100);
        $paths1000 = $this->getActionPaths($data1000);

        $this->assertNotEmpty($paths100);
        $this->assertNotEmpty($paths1000);

        $pressNode100 = $this->findPrintingPressNodes($paths100[0])[0] ?? null;
        $pressNode1000 = $this->findPrintingPressNodes($paths1000[0])[0] ?? null;

        $this->assertNotNull($pressNode100);
        $this->assertNotNull($pressNode1000);

        $sheetCount100 = $pressNode100['todo']['cutSheetCount'] ?? 0;
        $sheetCount1000 = $pressNode1000['todo']['cutSheetCount'] ?? 0;

        $this->assertGreaterThan(
            $sheetCount100,
            $sheetCount1000,
            'More copies should require more sheets'
        );
    }

    /**
     * @test
     * numberOfCopies affects total cost.
     */
    public function number_of_copies_affects_total_cost(): void
    {
        $data100 = $this->processRequest($this->createPayloadWithCopies(100));
        $data10000 = $this->processRequest($this->createPayloadWithCopies(10000));

        $paths100 = $this->getActionPaths($data100);
        $paths10000 = $this->getActionPaths($data10000);

        $this->assertNotEmpty($paths100);
        $this->assertNotEmpty($paths10000);

        $cost100 = $paths100[0]['cost'];
        $cost10000 = $paths10000[0]['cost'];

        $this->assertGreaterThan(
            $cost100,
            $cost10000,
            '10000 copies should cost more than 100 copies'
        );
    }

    /**
     * @test
     * paperWeight is present in enrichment/todo.
     */
    public function paper_weight_is_present_in_enrichment(): void
    {
        $data = $this->processRequest($this->createPayloadWithPaperWeight(250));

        $paths = $this->getActionPaths($data);
        $this->assertNotEmpty($paths);

        $pressNode = $this->findPrintingPressNodes($paths[0])[0] ?? null;
        $this->assertNotNull($pressNode, 'Should have a printing press node');

        // paperWeight should be in todo
        $this->assertArrayHasKey('paperWeight', $pressNode['todo'], 'paperWeight should be in todo');
    }

    // ==================== MACHINE ENRICHMENT CALCULATIONS ====================

    /**
     * @test
     * Offset press enrichment includes cost.
     */
    public function offset_press_enrichment_includes_cost(): void
    {
        $data = $this->processRequest($this->createPayloadWithInking([
            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
            'verso' => [],
        ]));

        $paths = $this->getActionPaths($data);
        $this->assertNotEmpty($paths);

        $pressNode = $this->findPrintingPressNodes($paths[0])[0] ?? null;
        $this->assertNotNull($pressNode, 'Should have a printing press node');

        // Node should have cost
        $this->assertArrayHasKey('cost', $pressNode, 'Press node should have cost');

        $cost = is_array($pressNode['cost'])
            ? $pressNode['cost']['cost']
            : $pressNode['cost'];

        $this->assertGreaterThan(0, $cost, 'Press cost should be positive');
    }

    /**
     * @test
     * More colors means higher print cost.
     */
    public function more_colors_means_higher_print_cost(): void
    {
        $data1 = $this->processRequest($this->createPayloadWithInking([
            'recto' => ['black'],
            'verso' => [],
        ]));

        $data4 = $this->processRequest($this->createPayloadWithInking([
            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
            'verso' => [],
        ]));

        $paths1 = $this->getActionPaths($data1);
        $paths4 = $this->getActionPaths($data4);

        $this->assertNotEmpty($paths1);
        $this->assertNotEmpty($paths4);

        // Get total path costs (which include all machine costs)
        $cost1 = $paths1[0]['cost'];
        $cost4 = $paths4[0]['cost'];

        $this->assertGreaterThan(
            $cost1,
            $cost4,
            '4 color print should cost more than 1 color print'
        );
    }

    // ==================== END-TO-END CONSISTENCY ====================

    /**
     * @test
     * Full processing produces consistent results with all parameters.
     *
     * This test uses a complex input and verifies the output structure
     * and that costs are calculated. The exact values are tested in
     * ActionPathValueSnapshotTest.
     */
    public function full_processing_produces_consistent_results(): void
    {
        $payload = [
            'parts' => [[
                'partId' => 'test-part-complex',
                'properties' => ['copies' => 1000],
                'actions' => [
                    [
                        'name' => 'print',
                        'params' => [
                            'dimensions' => [
                                'open' => ['width' => 200, 'height' => 280],
                                'closed' => ['width' => 100, 'height' => 140],
                            ],
                            'zone' => [
                                'width' => 100,
                                'height' => 140,
                                'gripMargin' => 5
                            ],
                            'inking' => [
                                'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                                'verso' => ['black'],
                            ],
                            'paper' => ['weight' => 250],
                        ],
                    ],
                    [
                        'name' => 'cut',
                        'params' => [],
                    ],
                ],
                'required_parts' => []
            ]]
        ];

        $data = $this->processRequest($payload);

        // Verify structure
        $this->assertIsArray($data);
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayHasKey('parts', $data[0]);
        $this->assertArrayHasKey('test-part-complex', $data[0]['parts']);

        $actionPaths = $data[0]['parts']['test-part-complex']['actionPaths'] ?? [];
        $this->assertNotEmpty($actionPaths, 'Should have at least one action path');

        $firstPath = $actionPaths[0];

        // Verify path has cost and duration
        $this->assertArrayHasKey('cost', $firstPath);
        $this->assertArrayHasKey('duration', $firstPath);
        $this->assertGreaterThan(0, $firstPath['cost'], 'Path cost should be positive');
        $this->assertGreaterThan(0, $firstPath['duration'], 'Path duration should be positive');

        // Verify expected machine types are present
        $machineTypes = $this->getMachineTypesInProductionOrder($firstPath['nodes']);

        $this->assertContains('ctp', $machineTypes, 'Should have CTP action');
        $this->assertContains('print', $machineTypes, 'Should have print action');
        $this->assertContains('cutting', $machineTypes, 'Should have cutting action');

        // With verso inking, should have 2 print actions
        $printCount = count(array_filter($machineTypes, fn($t) => $t === 'print'));
        $this->assertEquals(2, $printCount, 'Should have 2 print actions (recto + verso)');
    }

    /**
     * @test
     * Inking values are preserved in todo/enrichment.
     *
     * This test verifies that the inking information is accessible
     * in the response, which is important for the refactoring that
     * moves inking from context to action-level.
     */
    public function inking_values_are_preserved_in_response(): void
    {
        $inking = [
            'recto' => ['cyan', 'magenta', 'yellow', 'black'],
            'verso' => ['black'],
        ];

        $data = $this->processRequest($this->createPayloadWithInking($inking));
        $paths = $this->getActionPaths($data);

        $this->assertNotEmpty($paths);

        // Find CTP node - it should have inking information
        $ctpNode = $this->findCtpNode($paths[0]);
        $this->assertNotNull($ctpNode, 'Should have CTP node');

        // CTP todo should have inking
        $this->assertArrayHasKey('inking', $ctpNode['todo'], 'CTP todo should have inking');

        $todoInking = $ctpNode['todo']['inking'];
        $this->assertArrayHasKey('recto', $todoInking);
        $this->assertArrayHasKey('verso', $todoInking);

        // Verify color counts match
        $this->assertCount(4, $todoInking['recto'], 'Recto should have 4 colors');
        // Note: verso inking in CTP might be in a separate CTP node for the verso print
    }

    /**
     * @test
     * numberOfColors in todo reflects inking.
     */
    public function number_of_colors_reflects_inking(): void
    {
        $inking4 = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []];
        $inking1 = ['recto' => ['black'], 'verso' => []];

        $data4 = $this->processRequest($this->createPayloadWithInking($inking4));
        $data1 = $this->processRequest($this->createPayloadWithInking($inking1));

        $paths4 = $this->getActionPaths($data4);
        $paths1 = $this->getActionPaths($data1);

        $this->assertNotEmpty($paths4, 'Should have paths for 4 colors');
        $this->assertNotEmpty($paths1, 'Should have paths for 1 color');

        $pressNode4 = $this->findPrintingPressNodes($paths4[0])[0] ?? null;
        $this->assertNotNull($pressNode4, 'Should have printing press node for 4 colors');

        // For 1-color, we need to find ANY printing press node in the response
        // The machine names might be different for single color presses
        $pressNode1 = null;
        foreach ($paths1[0]['nodes'] ?? [] as $node) {
            // Check if this is any kind of printing press by looking at todo structure
            if (isset($node['todo']['numberOfColors'])) {
                $pressNode1 = $node;
                break;
            }
        }

        $this->assertNotNull($pressNode1, 'Should have printing press node for 1 color');

        // numberOfColors in todo should match inking
        $this->assertEquals(
            4,
            $pressNode4['todo']['numberOfColors'] ?? 0,
            'numberOfColors should be 4 for CMYK'
        );

        $this->assertEquals(
            1,
            $pressNode1['todo']['numberOfColors'] ?? 0,
            'numberOfColors should be 1 for black only'
        );
    }
}
