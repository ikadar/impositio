<?php

namespace App\Tests\Unit\Application\Process\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\DTO\ActionPathResult;
use App\Application\Process\Service\ActionPathRankerInterface;
use App\Application\Process\Service\ProductionPlanningService;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Service\ActionParamsExtractorInterface;
use App\Tests\Unit\Application\Process\ProcessTestBase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ProductionPlanningService.
 */
class ProductionPlanningServiceTest extends ProcessTestBase
{
    private ProductionPlanningService $service;
    private ActionTreeInterface|MockObject $actionTree;
    private ActionParamsExtractorInterface|MockObject $paramsExtractor;
    private ActionPathRankerInterface|MockObject $ranker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actionTree = $this->createMock(ActionTreeInterface::class);
        $this->paramsExtractor = $this->createMock(ActionParamsExtractorInterface::class);
        $this->ranker = $this->createMock(ActionPathRankerInterface::class);

        $this->service = new ProductionPlanningService(
            $this->actionTree,
            $this->paramsExtractor,
            $this->ranker
        );
    }

    /**
     * @test
     */
    public function plan_returns_empty_when_no_input_extracted(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [];

        $this->paramsExtractor
            ->method('extractForActionTree')
            ->willReturn(null);

        $result = $this->service->plan($part, $pressSheets);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function plan_calls_action_tree_with_correct_parameters(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [$this->createPressSheetMock(1000, 700)];
        $input = $this->createActionTreeInput();

        $this->paramsExtractor
            ->method('extractForActionTree')
            ->willReturn($input);

        $this->actionTree
            ->expects($this->once())
            ->method('process')
            ->with(
                $input->abstractActions,
                $input->pressSheets,
                $input->zone,
                $input->openPoseDimensions,
                $input->closedPoseDimensions,
                $input->numberOfCopies,
                $input->numberOfColors,
                $input->paperWeight,
                $input->inking
            )
            ->willReturn([]);

        $this->ranker->method('selectBest')->willReturn([]);

        $this->service->plan($part, $pressSheets);
    }

    /**
     * @test
     */
    public function plan_passes_paths_to_ranker(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [];
        $input = $this->createActionTreeInput();
        $rawPaths = [[$this->createNodeWithCost(100)], [$this->createNodeWithCost(200)]];

        $this->paramsExtractor->method('extractForActionTree')->willReturn($input);
        $this->actionTree->method('process')->willReturn($rawPaths);

        $this->ranker
            ->expects($this->once())
            ->method('selectBest')
            ->with($rawPaths)
            ->willReturn([]);

        $this->service->plan($part, $pressSheets);
    }

    /**
     * @test
     */
    public function plan_handles_exception_gracefully(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [];
        $input = $this->createActionTreeInput();

        $this->paramsExtractor->method('extractForActionTree')->willReturn($input);
        $this->actionTree->method('process')->willThrowException(new \RuntimeException('Test error'));

        $result = $this->service->plan($part, $pressSheets);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function plan_returns_action_path_results(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [];
        $input = $this->createActionTreeInput();
        $node = $this->createNodeWithCost(100);

        $this->paramsExtractor->method('extractForActionTree')->willReturn($input);
        $this->actionTree->method('process')->willReturn([[$node]]);
        $this->ranker->method('selectBest')->willReturn([[$node]]);
        $this->ranker->method('calculateCost')->willReturn(100.0);

        $result = $this->service->plan($part, $pressSheets);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(ActionPathResult::class, $result[0]);
        $this->assertEquals(100.0, $result[0]->totalCost);
    }

    /**
     * @test
     */
    public function plan_calculates_duration_from_nodes(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [];
        $input = $this->createActionTreeInput();
        $node = $this->createNodeWithCost(100); // Has 30 setup + 60 run = 90 duration

        $this->paramsExtractor->method('extractForActionTree')->willReturn($input);
        $this->actionTree->method('process')->willReturn([[$node]]);
        $this->ranker->method('selectBest')->willReturn([[$node]]);
        $this->ranker->method('calculateCost')->willReturn(100.0);

        $result = $this->service->plan($part, $pressSheets);

        $this->assertEquals(90.0, $result[0]->totalDuration);
    }

    /**
     * @test
     */
    public function plan_extracts_press_sheet_size(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [];
        $input = $this->createActionTreeInput();
        $node = $this->createNodeWithCost(100); // Has 1000x700 press sheet

        $this->paramsExtractor->method('extractForActionTree')->willReturn($input);
        $this->actionTree->method('process')->willReturn([[$node]]);
        $this->ranker->method('selectBest')->willReturn([[$node]]);
        $this->ranker->method('calculateCost')->willReturn(100.0);

        $result = $this->service->plan($part, $pressSheets);

        $this->assertEquals('1000x700', $result[0]->pressSheetSize);
    }

    /**
     * @test
     */
    public function getInput_returns_extractor_result(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [$this->createPressSheetMock(1000, 700)];
        $expectedInput = $this->createActionTreeInput();

        $this->paramsExtractor
            ->expects($this->once())
            ->method('extractForActionTree')
            ->with($part, $pressSheets)
            ->willReturn($expectedInput);

        $result = $this->service->getInput($part, $pressSheets);

        $this->assertSame($expectedInput, $result);
    }

    /**
     * @test
     */
    public function getInput_returns_null_when_extraction_fails(): void
    {
        $part = $this->createPartPayload();
        $pressSheets = [];

        $this->paramsExtractor
            ->method('extractForActionTree')
            ->willReturn(null);

        $result = $this->service->getInput($part, $pressSheets);

        $this->assertNull($result);
    }
}
