<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Enrichment\ActionEnrichmentInterface;
use App\Domain\Equipment\Enrichment\LegacyArrayEnrichment;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

class ActionPathNode implements Interfaces\ActionPathNodeInterface
{
    protected ActionEnrichmentInterface $enrichment;

    /**
     * @param array|ActionEnrichmentInterface $todoOrEnrichment For backward compatibility, accepts both array (legacy) and ActionEnrichmentInterface (new)
     */
    public function __construct(
        protected MachineInterface $machine,
        protected PressSheetInterface $pressSheet,
        protected InputSheetInterface $zone,
        protected GridFittingInterface $gridFitting,
        array|ActionEnrichmentInterface $todoOrEnrichment
    )
    {
        if ($todoOrEnrichment instanceof ActionEnrichmentInterface) {
            $this->enrichment = $todoOrEnrichment;
        } else {
            // Legacy array format - wrap in LegacyArrayEnrichment for backward compatibility
            $this->enrichment = new LegacyArrayEnrichment($todoOrEnrichment);
        }
    }

    public function getGridFitting(): GridFittingInterface
    {
        return $this->gridFitting;
    }

    public function setGridFitting(GridFittingInterface $gridFitting): ActionTreeNodeInterface
    {
        $this->gridFitting = $gridFitting;
        return $this;
    }

    public function getMachine(): MachineInterface
    {
        return $this->machine;
    }

    public function setMachine(MachineInterface $machine): Action
    {
        $this->machine = $machine;
        return $this;
    }

    public function getPressSheet(): PressSheetInterface
    {
        return $this->pressSheet;
    }

    public function setPressSheet(PressSheetInterface $pressSheet): Action
    {
        $this->pressSheet = $pressSheet;
        return $this;
    }

    public function getZone(): InputSheetInterface
    {
        return $this->zone;
    }

    public function setZone(InputSheetInterface $zone): Action
    {
        $this->zone = $zone;
        return $this;
    }

    public function calculateCost(): float|array
    {
        return $this->getMachine()->calculateCost($this);
    }

    public function calculateSetupDuration(): float
    {
        return $this->getMachine()->calculateSetupDuration($this);
    }

    public function calculateRunDuration(): float
    {
        return $this->getMachine()->calculateRunDuration($this);
    }

    /**
     * Get the enrichment data for this action.
     */
    public function getEnrichment(): ActionEnrichmentInterface
    {
        return $this->enrichment;
    }

    /**
     * @deprecated Use getEnrichment() instead. Will be removed in a future version.
     */
    public function getTodo(): array
    {
        return $this->enrichment->toArray();
    }

    /**
     * @deprecated Use constructor with ActionEnrichmentInterface instead.
     */
    public function setTodo(array $todo): static
    {
        $this->enrichment = new LegacyArrayEnrichment($todo);
        return $this;
    }

    public function toArray($machine, $pressSheet, $pose): array
    {
        $enrichmentArray = $this->enrichment->toArray();

        return [
            "machine" => $this->getMachine()->getId(),
            "zone" => [
                "width" => $this->getZone()->getWidth(),
                "height" => $this->getZone()->getHeight(),
            ],
            "pressSheet" => [
                "width" => $this->getPressSheet()->getWidth(),
                "height" => $this->getPressSheet()->getHeight(),
            ],
            "gridFitting" => [
                "cols" => $this->getGridFitting()->getCols(),
                "rows" => $this->getGridFitting()->getRows(),
                "rotated" => $this->getGridFitting()->isRotated(),
                "data" => $this->getGridFitting()->toArray($machine, $pressSheet, $pose),
            ],
            "trimLines" => $this->getGridFitting()->getTrimLines(),
            "setupDuration" => $this->calculateSetupDuration(),
            "runDuration" => $this->calculateRunDuration(),
            "cost" => $this->calculateCost(),
            "enrichment" => $enrichmentArray,
            // Backward compatibility: keep 'todo' key with same data
            "todo" => $enrichmentArray,
        ];
    }
}