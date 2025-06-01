<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

class ActionTree implements Interfaces\ActionTreeInterface
{
    protected array $root;

    protected DimensionsInterface $openPoseDimensions;
    protected ActionPathNodeInterface $action;
    protected array $previousActions = [];

    public function __construct(
        protected Calculator $layoutCalculator,
    )
    {}

    public function getOpenPoseDimensions(): DimensionsInterface
    {
        return $this->openPoseDimensions;
    }

    public function setOpenPoseDimensions(DimensionsInterface $openPoseDimensions): ActionTree
    {
        $this->openPoseDimensions = $openPoseDimensions;
        return $this;
    }



    public function calculate (
        array $abstractActions,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        array $prevNodes = []
    ): array
    {

        $abstractAction = array_shift($abstractActions);

        if ($abstractAction === null) {
            return $prevNodes;
        }

        $machine = $abstractAction->getAvailableMachines()[0];

        $action = new Action(
            $machine,
            $pressSheet,
            $zone,
            $this->layoutCalculator
        );

        if ($action->getMachine()->getType()->value === "folder") {
            $zone->setDimensions($this->getOpenPoseDimensions());
            $machine->setOpenPoseDimensions($this->getOpenPoseDimensions());

            $action = new Action(
                $machine,
                $pressSheet,
                $zone,
                $this->layoutCalculator
            );
        }

        // array of actionPathNodes
        $actionPaths = [];

        foreach ($action->getGridFittings() as $gridFitting) {
//            dump(sprintf("ZONE: %d, %d", $zone->getWidth(), $zone->getHeight()));
//            dump(sprintf("LOOP: %d, MACHINE: %s", count($action->getGridFittings()), $action->getMachine()->getId()));

            $gridFitting->getCutSheet()->setContentType("Sheet");

            $apn = new ActionTreeNode(
                $action->getMachine(),
                $action->getPressSheet(),
                $action->getZone(),
                $gridFitting,
                []
            );

            $apn->setPrevActions($this->calculate(
                $abstractActions,
                $pressSheet,
                $gridFitting->getCutSheet(),
                $prevNodes
            ));

            $actionPaths[] = $apn;
        }

        dump(count($actionPaths));

        return $actionPaths;
    }
}