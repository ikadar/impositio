<?php

namespace App\Domain\Action;

use App\Domain\Action\Interfaces\ActionPathNodeInterface;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Geometry\Interfaces\DimensionsInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;

class ActionTree implements Interfaces\ActionTreeInterface
{
    protected array $root;

    protected DimensionsInterface $openPoseDimensions;
    protected float $numberOfCopies;
    protected float $numberOfColors;
    protected float $paperWeight;


    protected ActionPathNodeInterface $action;
    protected array $previousActions = [];

    public function __construct(
        protected Calculator $layoutCalculator,
        protected EquipmentFactoryInterface $equipmentFactory,
    )
    {}

    public function getRoot(): array
    {
        return $this->root;
    }

    public function setRoot(array $root): ActionTree
    {
        $this->root = $root;
        return $this;
    }

    public function getOpenPoseDimensions(): DimensionsInterface
    {
        return $this->openPoseDimensions;
    }

    public function setOpenPoseDimensions(DimensionsInterface $openPoseDimensions): ActionTree
    {
        $this->openPoseDimensions = $openPoseDimensions;
        return $this;
    }

    public function getNumberOfCopies(): float
    {
        return $this->numberOfCopies;
    }

    public function setNumberOfCopies(float $numberOfCopies): ActionTree
    {
        $this->numberOfCopies = $numberOfCopies;
        return $this;
    }

    public function getNumberOfColors(): float
    {
        return $this->numberOfColors;
    }

    public function setNumberOfColors(float $numberOfColors): ActionTree
    {
        $this->numberOfColors = $numberOfColors;
        return $this;
    }

    public function getPaperWeight(): float
    {
        return $this->paperWeight;
    }

    public function setPaperWeight(float $paperWeight): ActionTree
    {
        $this->paperWeight = $paperWeight;
        return $this;
    }


    public function calculateTree(
        array $abstractActions,
        PressSheetInterface $pressSheet,
        InputSheetInterface $zone,
        array $prevNodes = []
    )
    {
        $this->setRoot($this->calculate($abstractActions, $pressSheet, $zone, $prevNodes));
        return $this->getRoot();
    }

    protected function calculate (
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

        $availableMachines = $abstractAction->getAvailableMachines();
//        $machine = $availableMachines[0];

        // array of actionPathNodes
        $actionPaths = [];

        foreach ($availableMachines as $machine) {
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
        }

        return $actionPaths;
    }

    public function flattenTree()
    {
        $reverseFlatActionPaths = [];
        foreach ($this->getRoot() as $action) {
            $reverseFlatActionPaths = array_merge($reverseFlatActionPaths, $this->flatten($action, []));
        }

        $flatActionPaths = [];
        foreach ($reverseFlatActionPaths as $reverseFlatActionPath) {
            $flatActionPaths[] = array_reverse($reverseFlatActionPath);
        }

        return $flatActionPaths;
    }

    protected function flatten (ActionTreeNodeInterface $node, array $path = []): array
    {
        $current = clone $node;
        $current->setPrevActions([]);
        $path[] = $current;

        if (empty($node->getPrevActions())) {
            return [$path];
        }

        $result = [];
        foreach ($node->getPrevActions() as $prevNode) {
            $subPaths = $this->flatten($prevNode, $path);
            foreach ($subPaths as $sp) {
                $result[] = $sp;
            }
        }

        return $result;
    }

    public function process(
        $abstractActions,
        $pressSheet,
        $zone,
        $openPoseDimensions,
        $numberOfCopies,
        $numberOfColors,
        $paperWeight,
    )
    {
        return $this->extendPaths(
            $abstractActions,
            $pressSheet,
            $zone,
            $openPoseDimensions,
            $numberOfCopies,
            $numberOfColors,
            $paperWeight,
        );
    }

    protected function extendPaths(
        $abstractActions,
        $pressSheet,
        $zone,
        $openPoseDimensions,
        $numberOfCopies,
        $numberOfColors,
        $paperWeight,
    ) {

        $this->setOpenPoseDimensions($openPoseDimensions);
        $this->setNumberOfCopies($numberOfCopies);
        $this->setNumberOfColors($numberOfColors);
        $this->setPaperWeight($paperWeight);

        $this->calculateTree($abstractActions, $pressSheet, $zone);
        $flatActionPaths = $this->flattenTree();

        $extendedFlatActionPaths = [];
        foreach ($flatActionPaths as $flatActionPath) {
            $extendedFlatActionPaths[] = $this->extend($flatActionPath);
        }
        return $extendedFlatActionPaths;
    }

    public function extend($flatActionPath)
    {
        $cutSheetCount = $this->getNumberOfCopies();

        $extendedActionPath = [];
        foreach ($flatActionPath as $loop => $nodeX) {

            $node = clone $nodeX;

            $nextAction = null;

            if (array_key_exists($loop+1, $flatActionPath)) {
                $nextAction = $flatActionPath[$loop+1];
            }


            if ($node->getMachine()->getType()->value === "printing press") {
                $ctpMachine = $this->equipmentFactory->fromId("ctp-machine");
                $ctpAction = new ActionPathNode(
                    $ctpMachine,
                    $node->getPressSheet(),
                    $node->getZone(),
                    clone $node->getGridFitting(),
                    [
                        "numberOfCopies" => $this->numberOfCopies,
                        "numberOfColors" => $this->numberOfColors,
                        "cutSheetCount" => $cutSheetCount
                    ]
                );


                $explanation = [
                    "machine" => [
                        "name" => $ctpMachine->getId(),
                        "minSheet" => $ctpMachine->getMinSheetDimensions(),
                        "maxSheet" => $ctpMachine->getMaxSheetDimensions(),
                    ]
                ];

                $ctpAction->getGridFitting()->setExplanation($explanation);
//                dump($node->getGridFitting()->getExplanation());
//                dump($node->getGridFitting()->toArray($this->equipmentFactory->fromId("ctp-machine"), $node->getPressSheet(), ["width" => 100, "height" => 100]));
//                dump($cuts->toArray($this->equipmentFactory->fromId("ctp-machine"), $node->getPressSheet(), ["width" => 100, "height" => 100]));
//                die();

                $extendedActionPath[] = $ctpAction;

                $node->setTodo([
                    "numberOfCopies" => $this->numberOfCopies,
                    "numberOfColors" => $this->numberOfColors,
                    "paperWeight" => $this->paperWeight,
                    "cutSheetCount" => $cutSheetCount
                ]);

            }

            if ($node->getMachine()->getType()->value === "folder") {
                $inputSheetLength = $this->openPoseDimensions->getHeight() / 1000;
                $node->setTodo([
                    "inputSheetLength" => $inputSheetLength,
                    "cutSheetCount" => $cutSheetCount,
                    "numberOfCopies" => $this->numberOfCopies,
                ]);
            }

            if ($node->getMachine()->getType()->value === "stitching machine") {
                $node->setTodo([
                    "numberOfCopies" => $this->numberOfCopies,
                    "cutSheetCount" => $cutSheetCount
                ]);
            }

            $extendedActionPath[] = $node;

            $numberOfTrimCuts = 0;
            $numberOfCutCuts = 0;
            if ($nextAction !== null) {

                if (
                    $node->getMachine()->getMaxSheetDimensions()->getWidth() !== $nextAction->getZone()->getDimensions()->getWidth()
                    ||
                    $node->getMachine()->getMaxSheetDimensions()->getHeight() !== $nextAction->getZone()->getDimensions()->getHeight()
                ) {
                    $numberOfTrimCuts = 0;
                    $numberOfTrimCuts += (($node->getGridFitting()->getTrimLines()["top"]["y"] > 0) ? 2 : 0);
                    $numberOfTrimCuts += (($node->getGridFitting()->getTrimLines()["left"]["x"] > 0) ? 2 : 0);
                }
                $numberOfCutCuts = $node->getGridFitting()->getCols() - 1 + $node->getGridFitting()->getRows() - 1;

            }

            $numberOfCuts = $numberOfTrimCuts + $numberOfCutCuts;

            if ($numberOfCuts > 0) {
                $cuts = new ActionPathNode(
                    $this->equipmentFactory->fromId("cutting-machine"),
                    $node->getPressSheet(),
                    $node->getZone(),
                    clone $node->getGridFitting(),
                    [
                        "numberOfCuts" => $numberOfCuts,
                        "numberOfCopies" => $this->numberOfCopies,
                        "numberOfColors" => $this->numberOfColors,
                        "paperWeight" => $this->paperWeight,
                        "cutSheetCount" => $cutSheetCount,
                        "trimCuts" => $numberOfTrimCuts,
                        "cuts" => $numberOfCutCuts,
                    ]
                );
                $extendedActionPath[] = $cuts;

            }

            if ($numberOfCutCuts > 0) {
                $cutSheetCount = $cutSheetCount * $node->getGridFitting()->getCols() * $node->getGridFitting()->getRows();
            }


        }

        return $extendedActionPath;
    }
}