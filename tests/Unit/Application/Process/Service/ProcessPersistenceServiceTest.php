<?php

namespace App\Tests\Unit\Application\Process\Service;

use App\Application\Process\PartPayload;
use App\Application\Process\ProcessRequestModel;
use App\Application\Process\Service\ProcessPersistenceService;
use App\Entity\ProcessActionPath;
use App\Entity\ProcessPart;
use App\Entity\ProcessRequest;
use App\Tests\Unit\Application\Process\ProcessTestBase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ProcessPersistenceService.
 */
class ProcessPersistenceServiceTest extends ProcessTestBase
{
    private ProcessPersistenceService $service;
    private EntityManagerInterface|MockObject $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new ProcessPersistenceService($this->em);
    }

    /**
     * @test
     */
    public function persist_calls_entity_manager_persist_and_flush(): void
    {
        $part = $this->createPartPayload();
        $request = new ProcessRequestModel([$part]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->persist($request, []);

        $this->assertInstanceOf(ProcessRequest::class, $result);
    }

    /**
     * @test
     */
    public function persist_creates_process_request_entity(): void
    {
        $part = $this->createPartPayload('PART001');
        $request = new ProcessRequestModel([$part]);

        $result = $this->service->persist($request, []);

        $this->assertInstanceOf(ProcessRequest::class, $result);
    }

    /**
     * @test
     */
    public function persist_creates_process_part_for_each_part(): void
    {
        $parts = [
            $this->createPartPayload('PART001'),
            $this->createPartPayload('PART002'),
        ];
        $request = new ProcessRequestModel($parts);

        $result = $this->service->persist($request, []);

        $this->assertCount(2, $result->getParts());
    }

    /**
     * @test
     */
    public function persist_sets_correct_part_id_on_process_parts(): void
    {
        $parts = [
            $this->createPartPayload('PART001'),
            $this->createPartPayload('PART002'),
        ];
        $request = new ProcessRequestModel($parts);

        $result = $this->service->persist($request, []);

        $partIds = array_map(
            fn(ProcessPart $p) => $p->getPartId(),
            $result->getParts()->toArray()
        );

        $this->assertContains('PART001', $partIds);
        $this->assertContains('PART002', $partIds);
    }

    /**
     * @test
     */
    public function persist_adds_action_paths_to_parts(): void
    {
        $part = $this->createPartPayload('PART001');
        $request = new ProcessRequestModel([$part]);
        $partsWithPaths = [
            'PART001' => [
                ['id' => 'uuid1', 'nodes' => [], 'cost' => 100],
                ['id' => 'uuid2', 'nodes' => [], 'cost' => 200],
            ],
        ];

        $result = $this->service->persist($request, $partsWithPaths);

        /** @var ProcessPart $processPart */
        $processPart = $result->getParts()->first();
        $this->assertCount(2, $processPart->getActionPaths());
    }

    /**
     * @test
     */
    public function persist_stores_action_path_json_data(): void
    {
        $part = $this->createPartPayload('PART001');
        $request = new ProcessRequestModel([$part]);
        $partsWithPaths = [
            'PART001' => [
                ['id' => 'uuid1', 'nodes' => ['node1'], 'cost' => 100, 'designation' => 'Test Path'],
            ],
        ];

        $result = $this->service->persist($request, $partsWithPaths);

        /** @var ProcessPart $processPart */
        $processPart = $result->getParts()->first();
        /** @var ProcessActionPath $actionPath */
        $actionPath = $processPart->getActionPaths()->first();

        $this->assertEquals('uuid1', $actionPath->getJson()['id']);
        $this->assertEquals('Test Path', $actionPath->getJson()['designation']);
        $this->assertEquals(100, $actionPath->getJson()['cost']);
    }

    /**
     * @test
     */
    public function persist_handles_empty_parts_array(): void
    {
        $request = new ProcessRequestModel([]);

        $result = $this->service->persist($request, []);

        $this->assertInstanceOf(ProcessRequest::class, $result);
        $this->assertCount(0, $result->getParts());
    }

    /**
     * @test
     */
    public function persist_handles_parts_without_action_paths(): void
    {
        $parts = [
            $this->createPartPayload('PART001'),
            $this->createPartPayload('PART002'),
        ];
        $request = new ProcessRequestModel($parts);
        // Only PART001 has action paths
        $partsWithPaths = [
            'PART001' => [
                ['id' => 'uuid1', 'nodes' => [], 'cost' => 100],
            ],
        ];

        $result = $this->service->persist($request, $partsWithPaths);

        $processParts = $result->getParts()->toArray();
        $part1 = $processParts[0];
        $part2 = $processParts[1];

        // PART001 should have 1 action path
        $this->assertCount(1, $part1->getActionPaths());
        // PART002 should have 0 action paths
        $this->assertCount(0, $part2->getActionPaths());
    }

    /**
     * @test
     */
    public function persist_stores_payload_with_parts_data(): void
    {
        $part = $this->createPartPayload('PART001');
        $request = new ProcessRequestModel([$part]);

        $result = $this->service->persist($request, []);

        $payload = $result->getPayload();
        $this->assertArrayHasKey('parts', $payload);
        $this->assertCount(1, $payload['parts']);
        $this->assertEquals('PART001', $payload['parts'][0]['partId']);
    }
}
