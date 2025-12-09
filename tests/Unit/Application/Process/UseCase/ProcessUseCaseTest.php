<?php

namespace App\Tests\Unit\Application\Process\UseCase;

use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\ProcessRequestModel;
use App\Application\Process\ProcessResponseModel;
use App\Application\Process\Service\ActionPathTransformerInterface;
use App\Application\Process\Service\ProcessPersistenceServiceInterface;
use App\Application\Process\Service\ProductionPlanningServiceInterface;
use App\Application\Process\UseCase\ProcessUseCase;
use App\Entity\ProcessRequest;
use App\Service\PressSheetProviderInterface;
use App\Tests\Unit\Application\Process\ProcessTestBase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the refactored ProcessUseCase.
 *
 * Tests the orchestration logic after SRP refactoring.
 */
class ProcessUseCaseTest extends ProcessTestBase
{
    private ProcessUseCase $useCase;
    private ProductionPlanningServiceInterface|MockObject $planningService;
    private ProcessPersistenceServiceInterface|MockObject $persistenceService;
    private ActionPathTransformerInterface|MockObject $transformer;
    private PressSheetProviderInterface|MockObject $pressSheetProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planningService = $this->createMock(ProductionPlanningServiceInterface::class);
        $this->persistenceService = $this->createMock(ProcessPersistenceServiceInterface::class);
        $this->transformer = $this->createMock(ActionPathTransformerInterface::class);
        $this->pressSheetProvider = $this->createMock(PressSheetProviderInterface::class);

        $this->useCase = new ProcessUseCase(
            $this->planningService,
            $this->persistenceService,
            $this->transformer,
            $this->pressSheetProvider
        );
    }

    /**
     * @test
     */
    public function execute_calls_planning_service_for_each_part(): void
    {
        $part1 = $this->createPartPayload('PART001');
        $part2 = $this->createPartPayload('PART002');
        $request = new ProcessRequestModel([$part1, $part2]);

        $pressSheets = [$this->createPressSheetMock(1000, 700)];
        $this->pressSheetProvider->method('getPressSheets')->willReturn($pressSheets);

        $this->planningService
            ->expects($this->exactly(2))
            ->method('plan')
            ->willReturn([]);

        $this->planningService->method('getInput')->willReturn(null);

        $processRequest = $this->createMock(ProcessRequest::class);
        $processRequest->method('getId')->willReturn(123);
        $this->persistenceService->method('persist')->willReturn($processRequest);

        $this->useCase->execute($request);
    }

    /**
     * @test
     */
    public function execute_transforms_results_for_response(): void
    {
        $part = $this->createPartPayload('PART001');
        $request = new ProcessRequestModel([$part]);

        $pressSheets = [$this->createPressSheetMock(1000, 700)];
        $this->pressSheetProvider->method('getPressSheets')->willReturn($pressSheets);

        $node = $this->createNodeWithCost(100);
        $actionPathResult = new ActionPathResult([$node], 100, 90, '1000x700');
        $this->planningService->method('plan')->willReturn([$actionPathResult]);
        $this->planningService->method('getInput')->willReturn($this->createActionTreeInput());

        $this->transformer
            ->expects($this->once())
            ->method('toResponseArray')
            ->willReturn(['id' => 'uuid', 'cost' => 100]);

        $processRequest = $this->createMock(ProcessRequest::class);
        $processRequest->method('getId')->willReturn(123);
        $this->persistenceService->method('persist')->willReturn($processRequest);

        $this->useCase->execute($request);
    }

    /**
     * @test
     */
    public function execute_persists_request_with_computed_paths(): void
    {
        $part = $this->createPartPayload('PART001');
        $request = new ProcessRequestModel([$part]);

        $pressSheets = [$this->createPressSheetMock(1000, 700)];
        $this->pressSheetProvider->method('getPressSheets')->willReturn($pressSheets);

        $node = $this->createNodeWithCost(100);
        $actionPathResult = new ActionPathResult([$node], 100, 90, '1000x700');
        $this->planningService->method('plan')->willReturn([$actionPathResult]);
        $this->planningService->method('getInput')->willReturn($this->createActionTreeInput());

        $this->transformer->method('toResponseArray')->willReturn(['id' => 'uuid', 'cost' => 100]);

        $processRequest = $this->createMock(ProcessRequest::class);
        $processRequest->method('getId')->willReturn(123);
        $this->persistenceService
            ->expects($this->once())
            ->method('persist')
            ->with($request, $this->isType('array'))
            ->willReturn($processRequest);

        $this->useCase->execute($request);
    }

    /**
     * @test
     */
    public function execute_returns_response_model_with_parts(): void
    {
        $part = $this->createPartPayload('PART001');
        $request = new ProcessRequestModel([$part]);

        $pressSheets = [$this->createPressSheetMock(1000, 700)];
        $this->pressSheetProvider->method('getPressSheets')->willReturn($pressSheets);

        $node = $this->createNodeWithCost(100);
        $actionPathResult = new ActionPathResult([$node], 100, 90, '1000x700');
        $this->planningService->method('plan')->willReturn([$actionPathResult]);
        $this->planningService->method('getInput')->willReturn($this->createActionTreeInput());

        $this->transformer->method('toResponseArray')->willReturn(['id' => 'uuid', 'cost' => 100]);

        $processRequest = $this->createMock(ProcessRequest::class);
        $processRequest->method('getId')->willReturn(123);
        $this->persistenceService->method('persist')->willReturn($processRequest);

        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(ProcessResponseModel::class, $response);
        $this->assertArrayHasKey('PART001', $response->parts);
    }

    /**
     * @test
     */
    public function execute_handles_empty_parts(): void
    {
        $request = new ProcessRequestModel([]);

        $processRequest = $this->createMock(ProcessRequest::class);
        $processRequest->method('getId')->willReturn(123);
        $this->persistenceService->method('persist')->willReturn($processRequest);

        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(ProcessResponseModel::class, $response);
        $this->assertEmpty($response->parts);
    }

    /**
     * @test
     */
    public function execute_extracts_paper_weight_from_print_action(): void
    {
        $part = $this->createPartPayload('PART001');
        $request = new ProcessRequestModel([$part]);

        $this->pressSheetProvider
            ->expects($this->once())
            ->method('getPressSheets')
            ->with(120.0) // Default paper weight from createPartPayload
            ->willReturn([]);

        $this->planningService->method('plan')->willReturn([]);
        $this->planningService->method('getInput')->willReturn(null);

        $processRequest = $this->createMock(ProcessRequest::class);
        $processRequest->method('getId')->willReturn(123);
        $this->persistenceService->method('persist')->willReturn($processRequest);

        $this->useCase->execute($request);
    }
}
