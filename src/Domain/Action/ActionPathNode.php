<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

class ActionPathNode implements Interfaces\ActionPathNodeInterface
{
    public function __construct(
        protected MachineInterface $machine,
        protected PressSheetInterface $pressSheet,
        protected InputSheetInterface $zone,
        protected GridFittingInterface $gridFitting,
        protected array $todo
    )
    {
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

    public function calculateCost()
    {
        return $this->getMachine()->calculateCost($this);
    }

    public function calculateSetupDuration()
    {
        return $this->getMachine()->calculateSetupDuration($this);
    }

    public function calculateRunDuration()
    {
        return $this->getMachine()->calculateRunDuration($this);
    }

    public function getTodo(): array
    {
        return $this->todo;
    }

    public function setTodo(array $todo): ActionPathNode
    {
        $this->todo = $todo;
        return $this;
    }

    public function toArray($machine, $pressSheet, $pose): array
    {
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
            "todo" => $this->getTodo()
        ];
    }
}