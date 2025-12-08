<?php

namespace App\Service;

use App\Application\Process\ActionTreeInput;
use App\Application\Process\PartPayload;
use App\Domain\Action\ActionName;
use App\Domain\Action\PayloadAction;
use App\Domain\Action\ProcessAbstractAction;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use App\Domain\Sheet\PrintFactory;

class ActionParamsExtractor implements ActionParamsExtractorInterface
{
    public function __construct(
        private EquipmentFactoryInterface $equipmentFactory,
        private PrintFactory $printFactory,
    ) {}

    /**
     * Extract ActionTree parameters from a PartPayload.
     *
     * @param PartPayload $part The part payload containing actions with params
     * @param PressSheetInterface[] $pressSheets Available press sheets
     * @return ActionTreeInput|null Returns null if no print action found or params invalid
     */
    public function extractForActionTree(PartPayload $part, array $pressSheets): ?ActionTreeInput
    {
        // Find print action to extract dimensions and other params
        $printAction = $this->findPrintAction($part->actions);
        if ($printAction === null) {
            return null;
        }

        $params = $printAction->params;

        // Extract dimensions
        $openDimensions = $this->extractOpenDimensions($params);
        $closedDimensions = $this->extractClosedDimensions($params);
        if ($openDimensions === null || $closedDimensions === null) {
            return null;
        }

        // Extract zone
        $zone = $this->extractZone($params, $closedDimensions);
        if ($zone === null) {
            return null;
        }

        // Extract inking
        $inking = $this->extractInking($params);

        // Calculate number of colors
        $numberOfColors = $this->calculateNumberOfColors($inking);

        // Extract paper weight
        $paperWeight = $this->extractPaperWeight($params);

        // Get number of copies from part (defaults to 1)
        $numberOfCopies = $part->properties['copies'] ?? 1;

        // Convert PayloadActions to ProcessAbstractActions
        $abstractActions = $this->convertToAbstractActions($part->actions);

        return new ActionTreeInput(
            abstractActions: $abstractActions,
            pressSheets: $pressSheets,
            zone: $zone,
            openPoseDimensions: $openDimensions,
            closedPoseDimensions: $closedDimensions,
            numberOfCopies: (float) $numberOfCopies,
            numberOfColors: (float) $numberOfColors,
            paperWeight: (float) $paperWeight,
            inking: $inking,
        );
    }

    /**
     * Find the print action in the actions array.
     */
    private function findPrintAction(array $actions): ?PayloadAction
    {
        foreach ($actions as $action) {
            if ($action->name === ActionName::Print) {
                return $action;
            }
        }
        return null;
    }

    /**
     * Extract open dimensions from params.
     */
    private function extractOpenDimensions(array $params): ?Dimensions
    {
        $dimensions = $params['dimensions'] ?? null;
        if ($dimensions === null) {
            return null;
        }

        $open = $dimensions['open'] ?? null;
        if ($open === null || !isset($open['width']) || !isset($open['height'])) {
            return null;
        }

        return new Dimensions(
            (float) $open['width'],
            (float) $open['height']
        );
    }

    /**
     * Extract closed dimensions from params.
     */
    private function extractClosedDimensions(array $params): ?Dimensions
    {
        $dimensions = $params['dimensions'] ?? null;
        if ($dimensions === null) {
            return null;
        }

        $closed = $dimensions['closed'] ?? null;
        if ($closed === null || !isset($closed['width']) || !isset($closed['height'])) {
            return null;
        }

        return new Dimensions(
            (float) $closed['width'],
            (float) $closed['height']
        );
    }

    /**
     * Extract zone (InputSheet) from params.
     */
    private function extractZone(array $params, Dimensions $closedDimensions): ?\App\Domain\Sheet\Interfaces\InputSheetInterface
    {
        $zoneParams = $params['zone'] ?? null;

        // Use zone params if provided, otherwise use closed dimensions
        $width = $zoneParams['width'] ?? $closedDimensions->getWidth();
        $height = $zoneParams['height'] ?? $closedDimensions->getHeight();
        $gripMargin = $zoneParams['gripMargin'] ?? 0;

        $zone = $this->printFactory->newInputSheet(
            'zone',
            0,
            0,
            (float) $width,
            (float) $height
        );
        $zone->setGripMarginSize((float) $gripMargin);
        $zone->setContentType('Zone');

        return $zone;
    }

    /**
     * Extract inking from params.
     */
    private function extractInking(array $params): array
    {
        $inking = $params['inking'] ?? [];

        return [
            'recto' => $inking['recto'] ?? [],
            'verso' => $inking['verso'] ?? [],
        ];
    }

    /**
     * Calculate total number of colors from inking.
     */
    private function calculateNumberOfColors(array $inking): int
    {
        $rectoColors = count($inking['recto'] ?? []);
        $versoColors = count($inking['verso'] ?? []);

        return $rectoColors + $versoColors;
    }

    /**
     * Extract paper weight from params.
     */
    private function extractPaperWeight(array $params): float
    {
        $paper = $params['paper'] ?? [];
        return (float) ($paper['weight'] ?? 0);
    }

    /**
     * Convert PayloadActions to ProcessAbstractActions.
     *
     * @param PayloadAction[] $actions
     * @return ProcessAbstractAction[]
     */
    private function convertToAbstractActions(array $actions): array
    {
        $abstractActions = [];

        foreach ($actions as $action) {
            $abstractActions[] = new ProcessAbstractAction(
                $action->name,
                $this->equipmentFactory
            );
        }

        return $abstractActions;
    }
}
