<?php

namespace App\Tests\Unit\Domain\Action;

use App\Domain\Action\ActionName;
use App\Domain\Action\ActionTree;
use App\Domain\Action\ActionTreeNode;
use App\Domain\Action\Interfaces\AbstractActionInterface;
use App\Domain\Action\Pipeline\ActionPathPipeline;
use App\Domain\Action\ProcessAbstractAction;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\Machine;
use App\Domain\Equipment\MachineType;
use App\Domain\Equipment\OffsetPrintingPress;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Base test class for ActionTree unit tests.
 * Provides mock objects and test fixtures for testing ActionTree methods.
 */
abstract class ActionTreeTestBase extends TestCase
{
    protected Calculator|MockObject $mockCalculator;
    protected PropertyAccessorInterface|MockObject $mockPropertyAccessor;
    protected ActionPathPipeline|MockObject $mockPipeline;

    // Test fixtures
    protected DimensionsInterface $openPoseDimensions;
    protected DimensionsInterface $closedPoseDimensions;
    protected PressSheetInterface|MockObject $pressSheet;
    protected InputSheetInterface|MockObject $zone;
    protected array $inking;
    protected array $abstractActions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCalculator = $this->createMock(Calculator::class);
        $this->mockPropertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $this->mockPipeline = $this->createMock(ActionPathPipeline::class);

        $this->setupTestFixtures();
    }

    protected function setupTestFixtures(): void
    {
        // Dimensions
        $this->openPoseDimensions = new Dimensions(200, 300);
        $this->closedPoseDimensions = new Dimensions(100, 150);

        // PressSheet mock
        $this->pressSheet = $this->createPressSheetMock(1000, 700, 80);

        // Zone (InputSheet) mock
        $this->zone = $this->createZoneMock(190, 140, 10);

        // Inking - recto only by default
        $this->inking = [
            'recto' => [1, 2],
            'verso' => []
        ];
    }

    /**
     * Create a mock PressSheet.
     */
    protected function createPressSheetMock(float $width, float $height, float $price = 0): PressSheetInterface|MockObject
    {
        $pressSheet = $this->createMock(PressSheetInterface::class);
        $pressSheet->method('getWidth')->willReturn($width);
        $pressSheet->method('getHeight')->willReturn($height);
        $pressSheet->method('getPrice')->willReturn($price);

        return $pressSheet;
    }

    /**
     * Create a mock Zone/InputSheet.
     */
    protected function createZoneMock(float $width, float $height, float $gripMargin = 0): InputSheetInterface|MockObject
    {
        $zoneDimensions = new Dimensions($width, $height);

        $zone = $this->createMock(InputSheetInterface::class);
        $zone->method('getWidth')->willReturn($width);
        $zone->method('getHeight')->willReturn($height);
        $zone->method('getGripMarginSize')->willReturn($gripMargin);
        $zone->method('getDimensions')->willReturn($zoneDimensions);
        $zone->method('getContentType')->willReturn('Sheet');

        return $zone;
    }

    /**
     * Create a mock Machine.
     * Uses OffsetPrintingPress for printing press types (supports getNumberOfColors),
     * and Machine for other types.
     */
    protected function createMachineMock(
        string $id,
        MachineType $type,
        int $numberOfColors = 4,
        ?int $maxPoseCount = null
    ): MachineInterface|MockObject {
        // Use OffsetPrintingPress for printing press (has getNumberOfColors)
        // Use Machine for other types
        $baseClass = ($type === MachineType::PrintingPress)
            ? OffsetPrintingPress::class
            : Machine::class;

        $methods = [
            'getId',
            'getType',
            'getMaxPoseCount',
            'getMinSheetDimensions',
            'getMaxSheetDimensions',
            'getGripMarginSize',
            'getMinSheetRectangle',
            'getMaxSheetRectangle',
            'calculateCost',
            'calculateSetupDuration',
            'calculateRunDuration',
        ];

        // Add getNumberOfColors for OffsetPrintingPress
        if ($baseClass === OffsetPrintingPress::class) {
            $methods[] = 'getNumberOfColors';
        }

        $machine = $this->getMockBuilder($baseClass)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();

        $machine->method('getId')->willReturn($id);
        $machine->method('getType')->willReturn($type);
        $machine->method('getMaxPoseCount')->willReturn($maxPoseCount);
        $machine->method('getMinSheetDimensions')->willReturn(new Dimensions(350, 250));
        $machine->method('getMaxSheetDimensions')->willReturn(new Dimensions(1020, 720));
        $machine->method('getGripMarginSize')->willReturn(10.0);
        $machine->method('calculateCost')->willReturn(100.0);
        $machine->method('calculateSetupDuration')->willReturn(30.0);
        $machine->method('calculateRunDuration')->willReturn(60.0);

        // Set numberOfColors for printing presses
        if ($baseClass === OffsetPrintingPress::class) {
            $machine->method('getNumberOfColors')->willReturn($numberOfColors);
        }

        return $machine;
    }

    /**
     * Create a mock AbstractAction.
     */
    protected function createAbstractActionMock(
        MachineType $machineType,
        array $availableMachines = []
    ): AbstractActionInterface|MockObject {
        $action = $this->createMock(AbstractActionInterface::class);
        $action->method('getMachineType')->willReturn($machineType);
        $action->method('getAvailableMachines')->willReturn($availableMachines);

        return $action;
    }

    /**
     * Create a mock GridFitting.
     */
    protected function createGridFittingMock(
        int $cols = 2,
        int $rows = 2,
        bool $rotated = false
    ): GridFittingInterface|MockObject {
        $cutSheet = $this->createZoneMock(400, 400, 10);

        $gridFitting = $this->createMock(GridFittingInterface::class);
        $gridFitting->method('getCols')->willReturn($cols);
        $gridFitting->method('getRows')->willReturn($rows);
        $gridFitting->method('isRotated')->willReturn($rotated);
        $gridFitting->method('getCutSheet')->willReturn($cutSheet);
        $gridFitting->method('getTrimLines')->willReturn([
            'top' => ['x' => 0, 'y' => 10, 'length' => 1000],
            'bottom' => ['x' => 0, 'y' => 690, 'length' => 1000],
            'left' => ['x' => 10, 'y' => 0, 'length' => 700],
            'right' => ['x' => 990, 'y' => 0, 'length' => 700],
        ]);

        return $gridFitting;
    }

    /**
     * Create an ActionTree instance with mock dependencies.
     * Pipeline is now required (Phase 6 - legacy code removed).
     */
    protected function createActionTree(): ActionTree
    {
        return new ActionTree(
            $this->mockCalculator,
            $this->mockPropertyAccessor,
            $this->mockPipeline
        );
    }

    /**
     * Create a configured ActionTree with standard test values set.
     */
    protected function createConfiguredActionTree(): ActionTree
    {
        $actionTree = $this->createActionTree();

        $actionTree->setOpenPoseDimensions($this->openPoseDimensions);
        $actionTree->setClosedPoseDimensions($this->closedPoseDimensions);
        $actionTree->setNumberOfCopies(1000);
        $actionTree->setNumberOfColors(4);
        $actionTree->setPaperWeight(80);
        $actionTree->setInking($this->inking);

        return $actionTree;
    }

    /**
     * Create an ActionTreeNode for testing.
     */
    protected function createActionTreeNode(
        MachineInterface|MockObject $machine = null,
        GridFittingInterface|MockObject $gridFitting = null,
        array $prevActions = []
    ): ActionTreeNode {
        $machine = $machine ?? $this->createMachineMock('test-machine', MachineType::PrintingPress);
        $gridFitting = $gridFitting ?? $this->createGridFittingMock();

        $node = new ActionTreeNode(
            $machine,
            $this->pressSheet,
            $this->zone,
            $gridFitting,
            []
        );

        $node->setPrevActions($prevActions);

        return $node;
    }

    /**
     * Create a simple tree structure for testing flatten methods.
     *
     * Structure:
     *     Root
     *    /    \
     * Child1  Child2
     *   |
     * Leaf1
     */
    protected function createSimpleTree(): ActionTreeNode
    {
        $leaf1 = $this->createActionTreeNode(
            $this->createMachineMock('leaf1', MachineType::CuttingMachine)
        );

        $child1 = $this->createActionTreeNode(
            $this->createMachineMock('child1', MachineType::PrintingPress),
            null,
            [$leaf1]
        );

        $child2 = $this->createActionTreeNode(
            $this->createMachineMock('child2', MachineType::PrintingPress)
        );

        $root = $this->createActionTreeNode(
            $this->createMachineMock('root', MachineType::CTPMachine),
            null,
            [$child1, $child2]
        );

        return $root;
    }

    /**
     * Create a deep tree structure for testing.
     *
     * Structure: Root → Child → Grandchild → Leaf
     */
    protected function createDeepTree(int $depth = 4): ActionTreeNode
    {
        $current = null;

        for ($i = $depth - 1; $i >= 0; $i--) {
            $node = $this->createActionTreeNode(
                $this->createMachineMock("node-{$i}", MachineType::PrintingPress),
                null,
                $current !== null ? [$current] : []
            );
            $current = $node;
        }

        return $current;
    }

    /**
     * Setup PropertyAccessor mock for inking access.
     */
    protected function setupInkingPropertyAccessor(array $inking): void
    {
        $this->mockPropertyAccessor
            ->method('getValue')
            ->willReturnCallback(function ($subject, $path) use ($inking) {
                if ($path === '[verso]') {
                    return $inking['verso'] ?? [];
                }
                if ($path === '[recto]') {
                    return $inking['recto'] ?? [];
                }
                return null;
            });
    }
}
