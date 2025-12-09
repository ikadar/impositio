<?php

namespace App\Tests\Unit\Domain\Action;

use App\Domain\Action\Pipeline\ActionPathContext;
use App\Domain\Action\Pipeline\ExtensionParams;
use App\Domain\Equipment\MachineType;

/**
 * Unit tests for ActionTree::extend() method.
 * Tests the pipeline-based extension logic.
 *
 * Phase 6: Legacy code removed. Pipeline is now required.
 */
class ActionTreeExtendTest extends ActionTreeTestBase
{
    /**
     * Test: extend() uses pipeline for path extension.
     */
    public function test_extend_uses_pipeline(): void
    {
        $node = $this->createActionTreeNode(
            $this->createMachineMock('test', MachineType::PrintingPress)
        );

        // Setup pipeline mock
        $expectedNodes = [$node];
        $mockContext = new ActionPathContext(
            $expectedNodes,
            1000,
            new ExtensionParams(1000, 4, 80, $this->inking, $this->openPoseDimensions, $this->closedPoseDimensions),
            [$node]
        );

        $this->mockPipeline
            ->expects($this->once())
            ->method('process')
            ->willReturn($mockContext);

        $actionTree = $this->createConfiguredActionTree();

        $result = $actionTree->extend([$node]);

        $this->assertIsArray($result);
        $this->assertEquals($expectedNodes, $result);
    }

    /**
     * Test: extend() creates correct ActionPathContext for pipeline.
     */
    public function test_extend_creates_correct_context(): void
    {
        $node = $this->createActionTreeNode(
            $this->createMachineMock('test', MachineType::PrintingPress)
        );

        // Capture the context passed to pipeline
        $capturedContext = null;
        $this->mockPipeline
            ->expects($this->once())
            ->method('process')
            ->willReturnCallback(function (ActionPathContext $context) use (&$capturedContext) {
                $capturedContext = $context;
                return $context;
            });

        $actionTree = $this->createConfiguredActionTree();
        $actionTree->extend([$node]);

        // Verify context was created with correct parameters
        $this->assertNotNull($capturedContext);
        $this->assertEquals(1000, $capturedContext->cutSheetCount);
        $this->assertEquals([$node], $capturedContext->originalPath);

        // Verify params
        $params = $capturedContext->params;
        $this->assertEquals(1000, $params->numberOfCopies);
        $this->assertEquals(4, $params->numberOfColors);
        $this->assertEquals(80, $params->paperWeight);
        $this->assertEquals($this->inking, $params->inking);
    }

    /**
     * Test: extend() with empty path returns empty result.
     */
    public function test_extend_with_empty_path(): void
    {
        $emptyContext = new ActionPathContext(
            [],
            1000,
            new ExtensionParams(1000, 4, 80, $this->inking, $this->openPoseDimensions, $this->closedPoseDimensions),
            []
        );

        $this->mockPipeline
            ->expects($this->once())
            ->method('process')
            ->willReturn($emptyContext);

        $actionTree = $this->createConfiguredActionTree();
        $result = $actionTree->extend([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
