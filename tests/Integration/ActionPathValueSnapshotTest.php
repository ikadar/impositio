<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Value-based snapshot tests for action path calculations.
 *
 * These tests capture the EXACT calculated values (costs, durations, etc.)
 * to detect any changes in the calculation logic during refactoring.
 *
 * Unlike structure snapshots, these tests verify that the numbers don't change.
 */
class ActionPathValueSnapshotTest extends WebTestCase
{
    private const SNAPSHOT_DIR = __DIR__ . '/../snapshots/action-path-values';

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }

    /**
     * Test: Simple CMYK print - captures exact cost and duration values.
     */
    public function test_simple_cmyk_print_values(): void
    {
        $payload = [
            'parts' => [[
                'partId' => 'VALUE_SNAPSHOT_001',
                'properties' => ['copies' => 1000],
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
                    ]
                ]],
                'required_parts' => []
            ]]
        ];

        $data = $this->processRequest($payload);
        $actionPaths = $data[0]['parts']['VALUE_SNAPSHOT_001']['actionPaths'] ?? [];

        $this->assertNotEmpty($actionPaths);

        // Extract value snapshot from first (cheapest) path
        $snapshot = $this->extractValueSnapshot($actionPaths[0]);

        $this->assertMatchesValueSnapshot('simple-cmyk-1000-copies', $snapshot);
    }

    /**
     * Test: Single color print - different cost calculation.
     */
    public function test_single_color_print_values(): void
    {
        $payload = [
            'parts' => [[
                'partId' => 'VALUE_SNAPSHOT_002',
                'properties' => ['copies' => 1000],
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
                            'recto' => ['black'],
                            'verso' => []
                        ],
                    ]
                ]],
                'required_parts' => []
            ]]
        ];

        $data = $this->processRequest($payload);
        $actionPaths = $data[0]['parts']['VALUE_SNAPSHOT_002']['actionPaths'] ?? [];

        $this->assertNotEmpty($actionPaths);

        $snapshot = $this->extractValueSnapshot($actionPaths[0]);

        $this->assertMatchesValueSnapshot('single-color-1000-copies', $snapshot);
    }

    /**
     * Test: High volume print - 10000 copies.
     */
    public function test_high_volume_print_values(): void
    {
        $payload = [
            'parts' => [[
                'partId' => 'VALUE_SNAPSHOT_003',
                'properties' => ['copies' => 10000],
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
                    ]
                ]],
                'required_parts' => []
            ]]
        ];

        $data = $this->processRequest($payload);
        $actionPaths = $data[0]['parts']['VALUE_SNAPSHOT_003']['actionPaths'] ?? [];

        $this->assertNotEmpty($actionPaths);

        $snapshot = $this->extractValueSnapshot($actionPaths[0]);

        $this->assertMatchesValueSnapshot('cmyk-10000-copies', $snapshot);
    }

    /**
     * Test: Verso (double-sided) print.
     */
    public function test_verso_print_values(): void
    {
        $payload = [
            'parts' => [[
                'partId' => 'VALUE_SNAPSHOT_004',
                'properties' => ['copies' => 1000],
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
                            'verso' => ['black']
                        ],
                    ]
                ]],
                'required_parts' => []
            ]]
        ];

        $data = $this->processRequest($payload);
        $actionPaths = $data[0]['parts']['VALUE_SNAPSHOT_004']['actionPaths'] ?? [];

        $this->assertNotEmpty($actionPaths);

        $snapshot = $this->extractValueSnapshot($actionPaths[0]);

        $this->assertMatchesValueSnapshot('verso-print-1000-copies', $snapshot);
    }

    /**
     * Test: Captures all unique grid fittings with their costs.
     */
    public function test_grid_fitting_cost_variations(): void
    {
        $payload = [
            'parts' => [[
                'partId' => 'VALUE_SNAPSHOT_005',
                'properties' => ['copies' => 1000],
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
                    ]
                ]],
                'required_parts' => []
            ]]
        ];

        $data = $this->processRequest($payload);
        $actionPaths = $data[0]['parts']['VALUE_SNAPSHOT_005']['actionPaths'] ?? [];

        // Capture grid fitting variations
        $gridFittingSnapshot = [];
        foreach ($actionPaths as $path) {
            $pressNode = $this->findNodeByMachine($path['nodes'], ['Komori G40', 'KBA 105']);
            if ($pressNode) {
                $gf = $pressNode['gridFitting'];
                $key = "{$gf['cols']}x{$gf['rows']}" . ($gf['rotated'] ? 'R' : 'U');
                if (!isset($gridFittingSnapshot[$key])) {
                    $gridFittingSnapshot[$key] = [
                        'cols' => $gf['cols'],
                        'rows' => $gf['rows'],
                        'rotated' => $gf['rotated'],
                        'pathCost' => $path['cost'],
                        'pathDuration' => $path['duration'],
                    ];
                }
            }
        }

        ksort($gridFittingSnapshot);

        $this->assertMatchesValueSnapshot('grid-fitting-variations', $gridFittingSnapshot);
    }

    /**
     * Extract a value snapshot from an action path.
     */
    private function extractValueSnapshot(array $path): array
    {
        $snapshot = [
            'pathCost' => $path['cost'],
            'pathDuration' => $path['duration'],
            'pressSheet' => $path['pressSheet'] ?? null,
            'nodeCount' => count($path['nodes']),
            'nodes' => [],
        ];

        foreach ($path['nodes'] as $node) {
            $nodeSnapshot = [
                'machine' => $node['machine'],
                'cost' => $node['cost'],
                'setupDuration' => $node['setupDuration'],
                'runDuration' => $node['runDuration'],
                'gridFitting' => [
                    'cols' => $node['gridFitting']['cols'],
                    'rows' => $node['gridFitting']['rows'],
                    'rotated' => $node['gridFitting']['rotated'],
                ],
                'todo' => $this->normalizeTodo($node['todo']),
            ];

            $snapshot['nodes'][] = $nodeSnapshot;
        }

        return $snapshot;
    }

    /**
     * Normalize todo for snapshot comparison.
     * Keeps only the important fields.
     */
    private function normalizeTodo(array $todo): array
    {
        $normalized = [];

        // Always include these if present
        $fields = ['numberOfCopies', 'numberOfColors', 'cutSheetCount', 'paperWeight'];
        foreach ($fields as $field) {
            if (isset($todo[$field])) {
                $normalized[$field] = $todo[$field];
            }
        }

        // Include cost breakdown
        if (isset($todo['cost'])) {
            $normalized['cost'] = $todo['cost'];
        }

        // Include inking structure (for CTP)
        if (isset($todo['inking'])) {
            $normalized['inking'] = [
                'rectoCount' => count($todo['inking']['recto'] ?? []),
                'versoCount' => count($todo['inking']['verso'] ?? []),
            ];
        }

        return $normalized;
    }

    /**
     * Find a node by machine ID.
     */
    private function findNodeByMachine(array $nodes, array $machineIds): ?array
    {
        foreach ($nodes as $node) {
            if (in_array($node['machine'], $machineIds)) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Send request to /process endpoint.
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
     * Assert value snapshot matches or create it.
     */
    private function assertMatchesValueSnapshot(string $name, array $data): void
    {
        if (!is_dir(self::SNAPSHOT_DIR)) {
            mkdir(self::SNAPSHOT_DIR, 0755, true);
        }

        $snapshotPath = self::SNAPSHOT_DIR . '/' . $name . '.json';

        if (!file_exists($snapshotPath)) {
            file_put_contents(
                $snapshotPath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $this->markTestIncomplete("Value snapshot created at {$snapshotPath}. Run test again to verify.");
            return;
        }

        $expected = json_decode(file_get_contents($snapshotPath), true);

        // Compare key values with small tolerance for floats
        $this->assertValueSnapshotMatches($expected, $data, $name);
    }

    /**
     * Compare snapshot values with tolerance for floats.
     */
    private function assertValueSnapshotMatches(array $expected, array $actual, string $name, string $path = ''): void
    {
        foreach ($expected as $key => $expectedValue) {
            $currentPath = $path ? "{$path}.{$key}" : $key;

            $this->assertArrayHasKey($key, $actual, "Missing key '{$currentPath}' in snapshot '{$name}'");

            $actualValue = $actual[$key];

            if (is_array($expectedValue)) {
                $this->assertIsArray($actualValue, "Expected array at '{$currentPath}' in snapshot '{$name}'");
                $this->assertValueSnapshotMatches($expectedValue, $actualValue, $name, $currentPath);
            } elseif (is_float($expectedValue) || is_float($actualValue)) {
                $this->assertEqualsWithDelta(
                    $expectedValue,
                    $actualValue,
                    0.01,
                    "Value mismatch at '{$currentPath}' in snapshot '{$name}'"
                );
            } else {
                $this->assertEquals(
                    $expectedValue,
                    $actualValue,
                    "Value mismatch at '{$currentPath}' in snapshot '{$name}'"
                );
            }
        }
    }
}
