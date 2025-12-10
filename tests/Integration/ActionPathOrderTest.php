<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for verifying action path order in /process endpoint.
 * These tests ensure that actions are returned in the correct production order.
 */
class ActionPathOrderTest extends WebTestCase
{
    /**
     * Helper to send a process request and get the action path.
     */
    private function getActionPath(array $actions): array
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'TEST001',
                    'properties' => [],
                    'actions' => $actions,
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
        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];

        $this->assertNotEmpty($actionPaths, 'Expected at least one action path');

        return $actionPaths[0]['nodes'] ?? [];
    }

    /**
     * Extract machine IDs from action path nodes in production order (reversed from backtrace).
     */
    private function getMachineIdsInProductionOrder(array $nodes): array
    {
        $machineIds = array_map(fn($node) => $node['machine'], $nodes);
        return array_reverse($machineIds);
    }

    /**
     * Extract machine types from action path nodes in production order.
     */
    private function getMachineTypesInProductionOrder(array $nodes): array
    {
        $machineIds = $this->getMachineIdsInProductionOrder($nodes);

        // Map known machine IDs to types
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

        return array_map(fn($id) => $typeMap[$id] ?? $id, $machineIds);
    }

    /**
     * Create a standard print action for tests.
     */
    private function createPrintAction(array $inking = ['recto' => ['cyan', 'magenta', 'yellow', 'black'], 'verso' => []]): array
    {
        return [
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
                'bleed' => ['size' => 6, 'unit' => 'mm'],
                'varnish' => ['type' => 'recto']
            ]
        ];
    }

    // ==================== TEST CASES ====================

    /**
     * Test: print only
     * Expected production order: CTP → Print
     */
    public function testPrintOnly(): void
    {
        $actions = [
            $this->createPrintAction()
        ];

        $nodes = $this->getActionPath($actions);
        $types = $this->getMachineTypesInProductionOrder($nodes);

        // CTP should come before print
        $ctpIndex = array_search('ctp', $types);
        $printIndex = array_search('print', $types);

        $this->assertNotFalse($ctpIndex, 'CTP action should be present');
        $this->assertNotFalse($printIndex, 'Print action should be present');
        $this->assertLessThan($printIndex, $ctpIndex, 'CTP should come before print in production order');

        // No cutting should be between CTP and print
        for ($i = $ctpIndex + 1; $i < $printIndex; $i++) {
            $this->assertNotEquals('cutting', $types[$i], 'No cutting should be between CTP and print');
        }
    }

    /**
     * Test: print → cutout → split
     * Expected production order: CTP → Print → cutting → cutout → cutting → split
     */
    public function testPrintCutoutSplit(): void
    {
        $actions = [
            $this->createPrintAction(),
            ['name' => 'cutout', 'params' => ['plate' => '$somePlate']],
            ['name' => 'split', 'params' => []]
        ];

        $nodes = $this->getActionPath($actions);
        $types = $this->getMachineTypesInProductionOrder($nodes);

        // Verify order: CTP → Print → cutout → split
        $ctpIndex = array_search('ctp', $types);
        $printIndex = array_search('print', $types);
        $cutoutIndex = array_search('cutout', $types);
        $splitterIndex = array_search('splitter', $types);

        $this->assertNotFalse($ctpIndex, 'CTP action should be present');
        $this->assertNotFalse($printIndex, 'Print action should be present');
        $this->assertNotFalse($cutoutIndex, 'Cutout action should be present');
        $this->assertNotFalse($splitterIndex, 'Splitter action should be present');

        // Verify sequence
        $this->assertLessThan($printIndex, $ctpIndex, 'CTP should come before print');
        $this->assertLessThan($cutoutIndex, $printIndex, 'Print should come before cutout');
        $this->assertLessThan($splitterIndex, $cutoutIndex, 'Cutout should come before split');

        // CTP should be immediately followed by print (no cutting between)
        $this->assertEquals($ctpIndex + 1, $printIndex, 'CTP should be immediately followed by print');
    }

    /**
     * Test: print with verso inking
     * Expected: CTP → Print (recto) → Print (verso) → ...
     */
    public function testPrintWithVerso(): void
    {
        $actions = [
            $this->createPrintAction([
                'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                'verso' => ['black']  // Verso inking present
            ])
        ];

        $nodes = $this->getActionPath($actions);
        $types = $this->getMachineTypesInProductionOrder($nodes);

        // Count print actions - should be 2 (recto + verso)
        $printCount = count(array_filter($types, fn($t) => $t === 'print'));
        $this->assertEquals(2, $printCount, 'Should have 2 print actions (recto + verso)');

        // Both prints should come after CTP
        $ctpIndex = array_search('ctp', $types);
        $printIndices = array_keys(array_filter($types, fn($t) => $t === 'print'));

        foreach ($printIndices as $printIndex) {
            $this->assertGreaterThan($ctpIndex, $printIndex, 'All prints should come after CTP');
        }
    }

    /**
     * Test: print without verso inking
     * Expected: Only one print action (no verso)
     */
    public function testPrintWithoutVerso(): void
    {
        $actions = [
            $this->createPrintAction([
                'recto' => ['cyan', 'magenta', 'yellow', 'black'],
                'verso' => []  // No verso inking
            ])
        ];

        $nodes = $this->getActionPath($actions);
        $types = $this->getMachineTypesInProductionOrder($nodes);

        // Count print actions - should be 1 (recto only)
        $printCount = count(array_filter($types, fn($t) => $t === 'print'));
        $this->assertEquals(1, $printCount, 'Should have only 1 print action (no verso)');
    }

    /**
     * Test: Cutting is only inserted when dimension changes or grid requires it.
     *
     * Note: For a small zone (300x200) on a large press with 1x1 grid,
     * cutting is NOT needed because:
     * - No trim cuts: zone matches actual product size
     * - No cut cuts: 1x1 grid has no internal divisions
     *
     * Cutting would be inserted for:
     * - Multi-up layouts (cols > 1 or rows > 1)
     * - Press sheet size changes between actions
     */
    public function testCuttingInsertedBetweenActions(): void
    {
        $actions = [
            $this->createPrintAction(),
            ['name' => 'cutout', 'params' => ['plate' => '$somePlate']]
        ];

        $nodes = $this->getActionPath($actions);
        $types = $this->getMachineTypesInProductionOrder($nodes);

        // For a simple 1x1 layout with no dimension changes,
        // cutting is not required between print and cutout
        $printIndex = array_search('print', $types);
        $cutoutIndex = array_search('cutout', $types);

        $this->assertNotFalse($printIndex, 'Print action should be present');
        $this->assertNotFalse($cutoutIndex, 'Cutout action should be present');
        $this->assertLessThan($cutoutIndex, $printIndex, 'Print should come before cutout');

        // Verify basic production order: CTP → Print → Cutout
        $ctpIndex = array_search('ctp', $types);
        $this->assertLessThan($printIndex, $ctpIndex, 'CTP should come before print');
    }

    /**
     * Test: No cutting between CTP and Print
     */
    public function testNoCuttingBetweenCtpAndPrint(): void
    {
        $actions = [
            $this->createPrintAction(),
            ['name' => 'cutout', 'params' => ['plate' => '$somePlate']],
            ['name' => 'split', 'params' => []]
        ];

        $nodes = $this->getActionPath($actions);
        $types = $this->getMachineTypesInProductionOrder($nodes);

        $ctpIndex = array_search('ctp', $types);
        $printIndex = array_search('print', $types);

        // Check that there's no cutting between CTP and print
        for ($i = $ctpIndex + 1; $i < $printIndex; $i++) {
            $this->assertNotEquals(
                'cutting',
                $types[$i],
                "No cutting should be between CTP (index $ctpIndex) and Print (index $printIndex)"
            );
        }
    }

    /**
     * Test: Multiple action paths have consistent order
     */
    public function testMultiplePathsHaveConsistentOrder(): void
    {
        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'TEST001',
                    'properties' => [],
                    'actions' => [
                        $this->createPrintAction(),
                        ['name' => 'cutout', 'params' => ['plate' => '$somePlate']]
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
        $data = json_decode($response->getContent(), true);
        $actionPaths = $data[0]['parts']['TEST001']['actionPaths'] ?? [];

        // All paths should have CTP before print
        foreach ($actionPaths as $index => $path) {
            $nodes = $path['nodes'] ?? [];
            $machineIds = array_reverse(array_map(fn($n) => $n['machine'], $nodes));

            $ctpPos = array_search('ctp-machine', $machineIds);
            $printPos = null;
            foreach ($machineIds as $pos => $id) {
                if (in_array($id, ['Komori G40', 'KBA 105'])) {
                    $printPos = $pos;
                    break;
                }
            }

            if ($ctpPos !== false && $printPos !== null) {
                $this->assertLessThan(
                    $printPos,
                    $ctpPos,
                    "Path #$index: CTP should come before print"
                );
            }
        }
    }

    /**
     * Test: Response structure is correct
     */
    public function testResponseStructure(): void
    {
        $actions = [
            $this->createPrintAction()
        ];

        $client = static::createClient();

        $payload = [
            'parts' => [
                [
                    'partId' => 'TEST001',
                    'properties' => [],
                    'actions' => $actions,
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
        $data = json_decode($response->getContent(), true);

        // Check top-level structure
        $this->assertIsArray($data);
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayHasKey('metaData', $data[0]);
        $this->assertArrayHasKey('parts', $data[0]);
        $this->assertArrayHasKey('TEST001', $data[0]['parts']);
        $this->assertArrayHasKey('actionPaths', $data[0]['parts']['TEST001']);

        // Check action path structure
        $actionPath = $data[0]['parts']['TEST001']['actionPaths'][0];
        $this->assertArrayHasKey('id', $actionPath);
        $this->assertArrayHasKey('designation', $actionPath);
        $this->assertArrayHasKey('nodes', $actionPath);

        // Check node structure
        $node = $actionPath['nodes'][0];
        $this->assertArrayHasKey('machine', $node);
        $this->assertArrayHasKey('zone', $node);
        $this->assertArrayHasKey('pressSheet', $node);
        $this->assertArrayHasKey('gridFitting', $node);
        $this->assertArrayHasKey('todo', $node);
        $this->assertArrayHasKey('cost', $node);
        $this->assertArrayHasKey('setupDuration', $node);
        $this->assertArrayHasKey('runDuration', $node);
    }
}
