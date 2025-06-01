<?php

namespace App\Controller;

use App\Domain\Action\AbstractAction;
use App\Domain\Action\Action;
use App\Domain\Action\ActionPathNode;
use App\Domain\Action\ActionTreeNode;
use App\Domain\Action\ActionType;
use App\Domain\Action\Interfaces\ActionInterface;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Domain\Action\Interfaces\ActionTreeNodeInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\MachineType;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\Interfaces\PressSheetInterface;
use App\Domain\Sheet\PrintFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    protected MachineInterface $machine;
    protected RectangleInterface $pressSheet;
    protected InputSheetInterface $zone;
    protected array $actionPath;
    protected array $pose;
    protected array $abstractActionData;

    public function __construct(
        protected Calculator $layoutCalculator,
        protected PrintFactory $printFactory,
        protected EquipmentServiceInterface $equipmentService,
        protected EquipmentFactoryInterface $equipmentFactory,
        protected ActionTreeInterface $actionTree,
    )
    {
    }

    #[Route(path: '/test', requirements: [], methods: ['POST'])]
    public function getTest(
    ): JsonResponse
    {
        $this->processPayload();

        // press sheet
        $this->pressSheet = $this->printFactory->newPressSheet(
            "pressSheet",
            0,
            0,
            1020,
            700,
            1
        );

        $this->openPoseDimensions = new Dimensions(
            420,
            296
        );

        $this->numberOfCopies = 1000;

        $this->paperWeight = 115;
        $this->numberOfColors = 4;


//        $abstractActions = [];
//        foreach ($this->abstractActionData as ["type" => $actionTypeName]) {
//            $abstractActions[]= new AbstractAction(
//                ActionType::tryFrom($actionTypeName),
//                $this->equipmentFactory
//            );
//        }
//
//        $flatActionPaths = $this->calculateFlatActionPaths($abstractActions, $this->pressSheet, $this->zone);
//
//        $extendedFlatActionPaths = [];
//        foreach ($flatActionPaths as $flatActionPath) {
//            $extendedFlatActionPaths[] = $this->extendFlatActionPath($flatActionPath);
//        }
//
//        $paths = [];
//        foreach ($extendedFlatActionPaths as $extendedFlatActionPath) {
//            $path = [
//                "designation" => [],
//                "nodes" => []
//            ];
//            $cost = 0;
//            $duration = 0;
//            foreach ($extendedFlatActionPath as $action) {
//                $actionArray = $action->toArray();
//                $cost += $actionArray["cost"];
//                $duration += ($actionArray["setupDuration"] + $actionArray["runDuration"]);
//                $path["designation"][] = $actionArray["machine"];
//
////                $path["designation"] .= sprintf(
////                    "- %s (%dx%d %s)<br/>",
////                    $actionArray["machine"],
////                    $actionArray["gridFitting"]["cols"],
////                    $actionArray["gridFitting"]["rows"],
////                    $actionArray["gridFitting"]["rotated"] ? "rotated" : "unrotated"
////                );
//                $path["nodes"][] = $actionArray;
//            }
//            $path["designation"] = implode(" > ", $path["designation"]);
//            $path["designation"] .= sprintf(" Cost: %s€; Duration: %smin", $cost, $duration);
//            $paths[] = $path;
//        }

//        return new JsonResponse(
//            $paths,
//        JsonResponse::HTTP_OK
//        );
//
//        die();

        $gridFittings = $this->layoutCalculator->calculateGridFittings(
            $this->machine,
            $this->pressSheet,
            $this->zone, // tile
        );

//        foreach ($gridFittings as $gridFitting) {
//            $actionTrees[] = new Action(
//                $this->machine,
//                $gridFitting
//            );
//        }

        return $this->createResponse($gridFittings, $this->actionPath);
    }

    protected function processPayload(): void
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $this->abstractActionData = $data['actions'];

        // machine
        $this->machine = $this->equipmentFactory->fromId($data["machine"]["id"]);

        // set openPoseDimensions
        $this->machine->setOpenPoseDimensions(new Dimensions(
            $data["openPose"]["width"],
            $data["openPose"]["height"]
        ));

        // set zone
        $zoneWidth = $data["zone"]["width"];
        $zoneHeight = $data["zone"]["height"];

        if ($this->machine->getType()->value === "folder") {
            $zoneWidth = $data["openPose"]["width"];
            $zoneHeight = $data["openPose"]["height"];
        }

        $this->zone = $this->printFactory->newInputSheet( // perhaps better to handle it as a Tile?
            "zone",
            0,
            0,
            $zoneWidth,
            $zoneHeight,
        );
        $this->zone->setGripMarginSize($data["zone"]["gripMargin"]["size"]);
        $this->zone->setContentType($data["zone"]["type"]); // todo: make it better



        $this->actionPath = $data["action-path"];
        $this->pose = $data["pose"];
        $this->openPose = $data["openPose"];
    }

    protected function createResponse($gridFittings, $actionPath): JsonResponse
    {
        $request = Request::createFromGlobals();
        $payload = json_decode($request->getContent(), true);

        $responseData = [];
        /**
         * @var GridFittingInterface $gridFitting
         */
        foreach ($gridFittings as $gridFitting) {

            $data = $gridFitting->toArray($this->machine, $this->pressSheet);

            if (($data["totalHeight"] > $data["pressSheet"]["height"]) || ($data["totalWidth"] > $data["pressSheet"]["width"])) {
                continue;
            }

            $data["actionPath"] = $actionPath;
            $data["pose"] = $this->pose;
            $data["openPose"] = $this->openPose;

            $responseData[] = $data;
        }

        return new JsonResponse(
            [
                "grid-fittings" => $responseData,
                "machines" => $payload["machines"]
            ],
            JsonResponse::HTTP_OK
        );

    }



    public function calculateFlatActionPaths($abstractActions, $pressSheet, $zone)
    {
        $this->actionTree->setOpenPoseDimensions($this->openPoseDimensions);
        $actionTrees = $this->actionTree->calculate($abstractActions, $pressSheet, $zone);
        dump("----------------");
        dump(count($actionTrees));
        die();

//        $actionTrees = $this->calculateActionTreeNodes($abstractActions, $pressSheet, $zone);

        $reverseFlatActionPaths = [];
        foreach ($actionTrees as $action) {
            $reverseFlatActionPaths = array_merge($reverseFlatActionPaths, $this->flattenActionTree($action, []));
        }

        $flatActionPaths = [];
        foreach ($reverseFlatActionPaths as $reverseFlatActionPath) {
            $flatActionPaths[] = array_reverse($reverseFlatActionPath);
        }

        return $flatActionPaths;
    }


    public function flattenActionTree(ActionTreeNodeInterface $node, array $path = []): array {
        $current = clone $node;
        $current->setPrevActions([]);
        $path[] = $current;

        if (empty($node->getPrevActions())) {
            return [$path];
        }

        $result = [];
        foreach ($node->getPrevActions() as $prevNode) {
            $subPaths = $this->flattenActionTree($prevNode, $path);
            foreach ($subPaths as $sp) {
                $result[] = $sp;
            }
        }

        return $result;
    }

    public function extendFlatActionPath($flatActionPath)
    {
        $cutSheetCount = $this->numberOfCopies;

        $extendedActionPath = [];
        foreach ($flatActionPath as $loop => $node) {
            $nextAction = null;

            if (array_key_exists($loop+1, $flatActionPath)) {
                $nextAction = $flatActionPath[$loop+1];
            }


            if ($node->getMachine()->getType()->value === "printing press") {
                $cuts = new ActionPathNode(
                    $this->equipmentFactory->fromId("ctp-machine"),
                    $node->getPressSheet(),
                    $node->getZone(),
                    $node->getGridFitting(),
                    [
                        "numberOfColors" => $this->numberOfColors
                    ]
                );
                $extendedActionPath[] = $cuts;

                $node->setTodo([
                    "numberOfCopies" => $this->numberOfCopies,
                    "numberOfColors" => $this->numberOfColors,
                    "paperWeight" => $this->paperWeight,
                ]);

            }

            if ($node->getMachine()->getType()->value === "folder") {
                $inputSheetLength = $this->openPoseDimensions->getHeight() / 1000;
                $node->setTodo([
                    "inputSheetLength" => $inputSheetLength,
                    "cutSheetCount" => $inputSheetLength,
                    "numberOfCopies" => $this->numberOfCopies,
                ]);
            }

            if ($node->getMachine()->getType()->value === "stitching machine") {
                $node->setTodo([
                    "numberOfCopies" => $this->numberOfCopies,
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
                    $flatActionPath[0]->getPressSheet(),
                    $flatActionPath[0]->getZone(),
                    $flatActionPath[0]->getGridFitting(),
                    [
                        "numberOfCuts" => $numberOfCuts,
                        "numberOfCopies" => $this->numberOfCopies,
                        "numberOfColors" => $this->numberOfColors,
                        "paperWeight" => $this->paperWeight,
                    ]
                );
                $extendedActionPath[] = $cuts;

                $cutSheetCount = $cutSheetCount * $node->getGridFitting()->getCols() * $node->getGridFitting()->getRows();

            }


        }

        return $extendedActionPath;
    }

}