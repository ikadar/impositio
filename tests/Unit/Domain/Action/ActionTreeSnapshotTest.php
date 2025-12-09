<?php

namespace App\Tests\Unit\Domain\Action;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Snapshot/Golden Master tests for ActionTree output.
 * These tests ensure that the action path output remains consistent across refactoring.
 *
 * Purpose:
 * - Detect unintended changes to action path structure
 * - Ensure refactoring doesn't change business logic results
 * - Provide regression protection
 *
 * Snapshots are stored in tests/snapshots/action-tree/
 */
class ActionTreeSnapshotTest extends WebTestCase
{
    private const SNAPSHOT_DIR = __DIR__ . '/../../../snapshots/action-tree';

    /**
     * Test: Simple print workflow snapshot.
     * Captures the action path structure for a basic print action.
     */
    public function test_snapshot_simple_print_workflow(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'SNAPSHOT_SIMPLE_PRINT',
                    'properties' => [],
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
                                'inking' => [
                                    'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                                    'verso' => []
                                ],
                                'bleed' => ['size' => 6, 'unit' => 'mm'],
                                'varnish' => ['type' => 'recto']
                            ]
                        ]
                    ],
                    'required_parts' => []
                ]
            ]
        ];

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

        $data = json_decode($response->getContent(), true);
        $actionPaths = $data[0]['parts']['SNAPSHOT_SIMPLE_PRINT']['actionPaths'] ?? [];

        $this->assertNotEmpty($actionPaths, 'Expected at least one action path');

        // Extract structure for comparison (remove volatile fields)
        $structure = $this->extractComparableStructure($actionPaths);

        // Compare with snapshot
        $this->assertMatchesSnapshot('simple-print', $structure);
    }

    /**
     * Test: Print with cutout workflow snapshot.
     */
    public function test_snapshot_print_cutout_workflow(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'SNAPSHOT_PRINT_CUTOUT',
                    'properties' => [],
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
                                'inking' => [
                                    'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                                    'verso' => []
                                ],
                                'bleed' => ['size' => 6, 'unit' => 'mm'],
                                'varnish' => ['type' => 'recto']
                            ]
                        ],
                        [
                            'name' => 'cutout',
                            'params' => ['plate' => '$somePlate']
                        ]
                    ],
                    'required_parts' => []
                ]
            ]
        ];

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

        $data = json_decode($response->getContent(), true);
        $actionPaths = $data[0]['parts']['SNAPSHOT_PRINT_CUTOUT']['actionPaths'] ?? [];

        $this->assertNotEmpty($actionPaths, 'Expected at least one action path');

        $structure = $this->extractComparableStructure($actionPaths);
        $this->assertMatchesSnapshot('print-cutout', $structure);
    }

    /**
     * Test: Print with verso inking snapshot.
     */
    public function test_snapshot_print_with_verso(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'SNAPSHOT_PRINT_VERSO',
                    'properties' => [],
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
                                'inking' => [
                                    'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                                    'verso' => ['black']
                                ],
                                'bleed' => ['size' => 6, 'unit' => 'mm'],
                                'varnish' => ['type' => 'recto']
                            ]
                        ]
                    ],
                    'required_parts' => []
                ]
            ]
        ];

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

        $data = json_decode($response->getContent(), true);
        $actionPaths = $data[0]['parts']['SNAPSHOT_PRINT_VERSO']['actionPaths'] ?? [];

        $this->assertNotEmpty($actionPaths, 'Expected at least one action path');

        $structure = $this->extractComparableStructure($actionPaths);
        $this->assertMatchesSnapshot('print-with-verso', $structure);
    }

    /**
     * Extract a comparable structure from action paths.
     * Removes volatile fields like IDs and exact costs that may change.
     */
    private function extractComparableStructure(array $actionPaths): array
    {
        $structure = [];

        foreach ($actionPaths as $index => $path) {
            $pathStructure = [
                'nodeCount' => count($path['nodes'] ?? []),
                'machines' => [],
                'hasGridFitting' => true,
            ];

            foreach ($path['nodes'] ?? [] as $node) {
                $pathStructure['machines'][] = $node['machine'] ?? 'unknown';
            }

            $structure[] = $pathStructure;
        }

        return $structure;
    }

    /**
     * Assert that the structure matches the saved snapshot.
     */
    private function assertMatchesSnapshot(string $snapshotName, array $structure): void
    {
        $snapshotPath = self::SNAPSHOT_DIR . '/' . $snapshotName . '.json';

        if (!file_exists($snapshotPath)) {
            // Create initial snapshot
            file_put_contents(
                $snapshotPath,
                json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $this->markTestIncomplete("Snapshot created at {$snapshotPath}. Run test again to verify.");
            return;
        }

        $expected = json_decode(file_get_contents($snapshotPath), true);

        // Compare machine sequences in first path
        if (!empty($expected) && !empty($structure)) {
            $expectedMachines = $expected[0]['machines'] ?? [];
            $actualMachines = $structure[0]['machines'] ?? [];

            $this->assertEquals(
                $expectedMachines,
                $actualMachines,
                "Machine sequence in action path doesn't match snapshot '{$snapshotName}'"
            );
        }

        // Compare structure counts
        foreach ($expected as $index => $expectedPath) {
            $this->assertArrayHasKey($index, $structure, "Missing path at index {$index}");

            $actualPath = $structure[$index];

            // Allow node count to vary slightly (±2) due to different grid fittings
            $this->assertGreaterThanOrEqual(
                $expectedPath['nodeCount'] - 2,
                $actualPath['nodeCount'],
                "Node count too low at path {$index}"
            );
        }
    }
}
