<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\ActionInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Layout\Interfaces\CalculatorInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

class Action implements ActionInterface
{
    protected array $gridFittings;
    public function __construct(
        protected MachineInterface $machine,
        protected PressSheetInterface $pressSheet,
        protected InputSheetInterface $zone,
        protected CalculatorInterface $calculator
    )
    {
        $this->calculateGridFittings();
    }

    public function getGridFittings(): array
    {
        return $this->gridFittings;
    }

    public function setGridFittings(array $gridFittings): Action
    {
        $this->gridFittings = $gridFittings;
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

    protected function calculateGridFittings()
    {
        $this->setGridFittings($this->calculator->calculateGridFittings(
            $this->machine,
            $this->pressSheet,
            $this->zone, // tile
        ));
    }

    public function toArray(): array
    {
        $array =  [
            "machine" => $this->getMachine()->getId(),
            "zone" => [
                "width" => $this->getZone()->getWidth(),
                "height" => $this->getZone()->getHeight(),
            ],
            "pressSheet" => [
                "width" => $this->getPressSheet()->getWidth(),
                "height" => $this->getPressSheet()->getHeight(),
            ],
            "prevActions" => []
        ];

        foreach ($this->prevActions as $prevAction) {
            $gf = $prevAction["gridFitting"]->toArray(
                $prevAction["action"]->getMachine(),
                $prevAction["action"]->getPressSheet()
            );
            $array["prevActions"][] = [
                "gridFitting" => [
                    "cols" => $gf["cols"],
                    "rows" => $gf["rows"],
                    "rotated" => $gf["rotated"]
                ],
                "action" => $prevAction["action"]->toArray(),
            ];
        }

        return $array;
    }

}