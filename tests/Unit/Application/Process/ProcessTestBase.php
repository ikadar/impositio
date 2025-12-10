<?php

namespace App\Tests\Unit\Application\Process;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\PartPayload;
use App\Domain\Action\ActionName;
use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\PayloadAction;
use App\Domain\Equipment\Enrichment\ActionEnrichmentInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\MachineType;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Base test class for Process-related unit tests.
 * Provides mock factories and test fixtures.
 */
abstract class ProcessTestBase extends TestCase
{
    protected DimensionsInterface $openPoseDimensions;
    protected DimensionsInterface $closedPoseDimensions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openPoseDimensions = new Dimensions(200, 300);
        $this->closedPoseDimensions = new Dimensions(100, 150);
    }

    /**
     * Create a mock ActionPathNode with specified cost.
     */
    protected function createNodeWithCost(float $cost): ActionPathNodeInterface|MockObject
    {
        $node = $this->createMock(ActionPathNodeInterface::class);
        $node->method('getTodo')->willReturn(['cost' => $cost]);
        $node->method('calculateSetupDuration')->willReturn(30.0);
        $node->method('calculateRunDuration')->willReturn(60.0);

        // Mock enrichment for getEnrichment()->getCost()
        $enrichment = $this->createMock(ActionEnrichmentInterface::class);
        $enrichment->method('getCost')->willReturn($cost);
        $enrichment->method('getCutSheetCount')->willReturn(100.0);
        $enrichment->method('toArray')->willReturn(['cost' => $cost]);
        $node->method('getEnrichment')->willReturn($enrichment);

        $machine = $this->createMachineMock('test-machine', MachineType::PrintingPress);
        $node->method('getMachine')->willReturn($machine);

        $pressSheet = $this->createPressSheetMock(1000, 700);
        $node->method('getPressSheet')->willReturn($pressSheet);

        $gridFitting = $this->createGridFittingMock();
        $node->method('getGridFitting')->willReturn($gridFitting);

        $zone = $this->createZoneMock(200, 200);
        $node->method('getZone')->willReturn($zone);

        $node->method('toArray')->willReturn([
            'machine' => 'test-machine',
            'pressSheet' => ['width' => 1000, 'height' => 700],
            'cost' => $cost,
            'setupDuration' => 30,
            'runDuration' => 60,
        ]);

        return $node;
    }

    /**
     * Create a mock ActionPathNode with array cost format.
     */
    protected function createNodeWithArrayCost(array $costArray): ActionPathNodeInterface|MockObject
    {
        $node = $this->createMock(ActionPathNodeInterface::class);
        $node->method('getTodo')->willReturn(['cost' => $costArray]);
        $node->method('calculateSetupDuration')->willReturn(30.0);
        $node->method('calculateRunDuration')->willReturn(60.0);

        // Mock enrichment - extract 'cost' from array
        $cost = $costArray['cost'] ?? 0;
        $enrichment = $this->createMock(ActionEnrichmentInterface::class);
        $enrichment->method('getCost')->willReturn((float) $cost);
        $enrichment->method('getCutSheetCount')->willReturn(100.0);
        $enrichment->method('toArray')->willReturn(['cost' => $costArray]);
        $node->method('getEnrichment')->willReturn($enrichment);

        $machine = $this->createMachineMock('test-machine', MachineType::PrintingPress);
        $node->method('getMachine')->willReturn($machine);

        $pressSheet = $this->createPressSheetMock(1000, 700);
        $node->method('getPressSheet')->willReturn($pressSheet);

        $gridFitting = $this->createGridFittingMock();
        $node->method('getGridFitting')->willReturn($gridFitting);

        $zone = $this->createZoneMock(200, 200);
        $node->method('getZone')->willReturn($zone);

        $node->method('toArray')->willReturn([
            'machine' => 'test-machine',
            'pressSheet' => ['width' => 1000, 'height' => 700],
            'cost' => $costArray,
            'setupDuration' => 30,
            'runDuration' => 60,
        ]);

        return $node;
    }

    /**
     * Create an action path (array of nodes) with specified total cost.
     */
    protected function createPathWithCost(float $cost): array
    {
        return [$this->createNodeWithCost($cost)];
    }

    /**
     * Create a mock Machine.
     */
    protected function createMachineMock(string $id, MachineType $type): MachineInterface|MockObject
    {
        $machine = $this->createMock(MachineInterface::class);
        $machine->method('getId')->willReturn($id);
        $machine->method('getType')->willReturn($type);
        $machine->method('getMinSheetDimensions')->willReturn(new Dimensions(350, 250));
        $machine->method('getMaxSheetDimensions')->willReturn(new Dimensions(1020, 720));

        return $machine;
    }

    /**
     * Create a mock PressSheet.
     */
    protected function createPressSheetMock(float $width, float $height): PressSheetInterface|MockObject
    {
        $pressSheet = $this->createMock(PressSheetInterface::class);
        $pressSheet->method('getWidth')->willReturn($width);
        $pressSheet->method('getHeight')->willReturn($height);

        return $pressSheet;
    }

    /**
     * Create a mock Zone/InputSheet.
     */
    protected function createZoneMock(float $width, float $height): InputSheetInterface|MockObject
    {
        $zone = $this->createMock(InputSheetInterface::class);
        $zone->method('getWidth')->willReturn($width);
        $zone->method('getHeight')->willReturn($height);
        $zone->method('getDimensions')->willReturn(new Dimensions($width, $height));

        return $zone;
    }

    /**
     * Create a mock GridFitting.
     */
    protected function createGridFittingMock(int $cols = 2, int $rows = 2): GridFittingInterface|MockObject
    {
        $gridFitting = $this->createMock(GridFittingInterface::class);
        $gridFitting->method('getCols')->willReturn($cols);
        $gridFitting->method('getRows')->willReturn($rows);

        return $gridFitting;
    }

    /**
     * Create a PartPayload for testing.
     */
    protected function createPartPayload(
        string $partId = 'TEST001',
        array $properties = [],
        array $actions = null,
        array $requiredParts = []
    ): PartPayload {
        if ($actions === null) {
            $actions = [new PayloadAction(ActionName::Print, [
                'dimensions' => [
                    'open' => ['width' => 200, 'height' => 300],
                    'closed' => ['width' => 100, 'height' => 150],
                ],
                'inking' => ['recto' => ['black'], 'verso' => []],
            ])];
        }

        return new PartPayload($partId, $properties, $actions, $requiredParts);
    }

    /**
     * Create an ActionTreeInput for testing.
     */
    protected function createActionTreeInput(
        ?DimensionsInterface $openDimensions = null,
        ?DimensionsInterface $closedDimensions = null,
        float $numberOfCopies = 1000,
        float $numberOfColors = 4,
        float $paperWeight = 80,
        array $inking = null
    ): ActionTreeInput {
        $inking = $inking ?? ['recto' => ['black'], 'verso' => []];

        return new ActionTreeInput(
            abstractActions: [],
            pressSheets: [$this->createPressSheetMock(1000, 700)],
            zone: $this->createZoneMock(200, 200),
            openPoseDimensions: $openDimensions ?? $this->openPoseDimensions,
            closedPoseDimensions: $closedDimensions ?? $this->closedPoseDimensions,
            numberOfCopies: $numberOfCopies,
            numberOfColors: $numberOfColors,
            paperWeight: $paperWeight,
            inking: $inking,
        );
    }
}
