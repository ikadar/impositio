<?php

namespace App\Controller;

use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\Machine;
use App\Domain\Equipment\MachineType;
use App\Domain\Equipment\OffsetPrintingPress;
use App\Domain\Equipment\PrintingPress;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\PrintFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Annotation\Route;

class Test3Controller extends AbstractController
{
    protected MachineInterface $machine;
    protected RectangleInterface $pressSheet;
    protected InputSheetInterface $zone;
    protected array $actionPath;
    protected array $pose;

    protected array $actionPaths = [];

    public function __construct(
        protected Calculator $layoutCalculator,
        protected PrintFactory $printFactory,
        protected EquipmentServiceInterface $equipmentService,
    )
    {
        $this->config = <<<CFG
{
  "actions": [
    {
      "type": "stitching",
      "machine": "Hohner"
    },
    {
      "type": "folding",
      "machine": "MBO XL"
    },
    {
      "type": "printing",
      "machine": "Komori G40"
    }
  ],
  "number-of-colors": 4,
  "number-of-copies": 1000,
  "paper-weight": 115,
  "press-sheet": {
    "width": 1020,
    "height": 700,
    "gripMargin": 20,
    "price": 1
  },
  "pose": {
    "width": 210,
    "height": 148,
    "width1": 105,
    "height1": 74
  },
  "openPose": {
    "width": 420,
    "height": 296
  },
  "zone": {
    "width": 210,
    "height": 148,
    "type": "Zone",
    "gripMargin": {
      "size": 0,
      "position": null,
      "x": 0,
      "y": 0,
      "width": 0,
      "height": 0
    }
  },
  "zone-A3": {
    "width": 420,
    "height": 297,
    "type": "Zone",
    "gripMargin": {
      "size": 0,
      "position": null,
      "x": 0,
      "y": 0,
      "width": 0,
      "height": 0
    }
  },
  "zone-A4": {
    "width": 297,
    "height": 210,
    "type": "Zone",
    "gripMargin": {
      "size": 0,
      "position": null,
      "x": 0,
      "y": 0,
      "width": 0,
      "height": 0
    }
  },
  "zone-TEST": {
    "width": 120,
    "height": 80,
    "type": "Zone",
    "grip-margin": {
      "size": 0,
      "position": null,
      "x": 0,
      "y": 0,
      "width": 0,
      "height": 0
    }
  },
  "action-path": {
  }
}
CFG;

        $this->config = json_decode($this->config, true);

        $equipments = $this->equipmentService->load();
        $accessor = PropertyAccess::createPropertyAccessor();
        $machines = [];
        foreach ($this->config["actions"] as $action) {
            $machine = $accessor->getValue($equipments, "[" . $action["machine"] . "]");
            $machines[] = $machine;
        }

        $this->config["machines"] = $machines;


    }

    #[Route(path: '/test3', requirements: [], methods: ['POST'])]
    public function getTest(
    ): JsonResponse
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $this->initialData = $data;


        $responseData = $this->calculateActionPathTree($data, 0);

        $this->flattenActionPathTree($responseData, []);

        $x = $this->createExplanation($this->actionPaths[0]["actions"]);
        echo(json_encode($x));
        die();
//        echo(json_encode($this->actionPaths));
//        die("OK");

        return $this->createResponse($this->actionPaths);


        return $this->createResponse($responseData);
    }

    protected function calculateActionPathTree($data, $level)
    {
        list($machine, $pressSheet, $zone, $actionPath) = $this->processPayload($data);

        $gridFittings = $this->layoutCalculator->calculateGridFittings(
            $machine,
            $pressSheet,
            $zone, // tile
        );

        $responseData = $this->createResponseData($gridFittings, $actionPath);

        $level++;

        if (!array_key_exists($level, $this->initialData["machines"])) {
            return $responseData;
        }

        foreach ($responseData["grid-fittings"] as $loop => $gridFitting) {

            $payload = $this->initialData;
            $payload["action-path"][$machine->getId()] = $gridFitting;
            $payload["machine"] = $payload["machines"][$level];
            $payload["zone"] = $gridFitting["cutSheet"];

            $payload["cutSpacing"] = [
                "horizontal" => 0,
                "vertical" => 0
            ];

            $responseData["grid-fittings"][$loop]["prevActions"][] = $this->calculateActionPathTree($payload, $level);
        }

        return $responseData;
    }

    protected function processPayload($data): array
    {
//        $request = Request::createFromGlobals();
//        $data = json_decode($request->getContent(), true);
        
        $accessor = PropertyAccess::createPropertyAccessor();

        $machineType = MachineType::tryFrom($data["machine"]["type"]);
        if ($machineType === null) {
            throw new BadRequestHttpException();
        }


        if ($accessor->getValue($data, "[machine][input-sheet-dimensions]")) {
            $minDimensions = new Dimensions(
                $data["machine"]["input-sheet-dimensions"]["width"],
                $data["machine"]["input-sheet-dimensions"]["height"]
            );
            $maxDimensions = new Dimensions(
                $data["machine"]["input-sheet-dimensions"]["width"],
                $data["machine"]["input-sheet-dimensions"]["height"]
            );
        } else {
            $minDimensions = new Dimensions(
                $data["machine"]["input-dimensions"]["min"]["width"],
                $data["machine"]["input-dimensions"]["min"]["height"]
            );
            $maxDimensions = new Dimensions(
                $data["machine"]["input-dimensions"]["max"]["width"],
                $data["machine"]["input-dimensions"]["max"]["height"]
            );
        }

        if ($machineType === MachineType::PrintingPress) {

            $options = [
                "base-setup-duration" => $data["machine"]["base-setup-duration"],
                "setup-duration-per-color" => $data["machine"]["setup-duration-per-color"],
                "two-pass" => $data["machine"]["two-pass"],
                "pass-per-color" => $data["machine"]["pass-per-color"],
                "sheets-per-hour" => $data["machine"]["sheets-per-hour"],
                "max-input-stack-height" => $data["machine"]["max-input-stack-height"],
                "stack-replenishment-duration" => $data["machine"]["stack-replenishment-duration"],
            ];

            $this->machine = new OffsetPrintingPress(
                $data["machine"]["id"],
                $machineType,
                $data["machine"]["gripMargin"],
                $minDimensions,
                $maxDimensions,
                $this->printFactory,
                $options
            );

        } else {
            $this->machine = new Machine(
                $data["machine"]["id"],
                $machineType,
                $data["machine"]["gripMargin"],
                $minDimensions,
                $maxDimensions,
                $this->printFactory
            );
        }


        $this->pressSheet = $this->printFactory->newRectangle(
            "pressSheet",
            0,
            0,
            $data["press-sheet"]["width"],
            $data["press-sheet"]["height"]
        );
        $this->pressSheet->price = $data["press-sheet"]["price"];

        $zoneWidth = $data["zone"]["width"];
        $zoneHeight = $data["zone"]["height"];

        if ($this->machine->getType()->value === "folder") {

            $zoneWidth = $data["openPose"]["width"];
            $zoneHeight = $data["openPose"]["height"];

            $this->machine->setMinSheetDimensions(new Dimensions(
                $zoneWidth,
                $zoneHeight
            ));

            $this->machine->setMaxSheetDimensions(new Dimensions(
                $zoneWidth,
                $zoneHeight
            ));

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

        return [
            $this->machine,
            $this->pressSheet,
            $this->zone,
            $this->actionPath
        ];

    }

    protected function createResponseData($gridFittings, $actionPath): array
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

        return
            [
                "grid-fittings" => $responseData,
                "machines" => $payload["machines"]
            ];

    }

    protected function createResponse($responseData): JsonResponse
    {
        return new JsonResponse(
            $responseData,
            JsonResponse::HTTP_OK
        );

    }

    protected function flattenActionPathTree($actionPathTree, $prevActions)
    {
        foreach ($actionPathTree["grid-fittings"] as $gridFitting) {
            $gridFitting["_"] = sprintf(
                "%s - %sx%s - %s",
                $gridFitting["explanation"]["machine"]["name"],
                $gridFitting["cols"],
                $gridFitting["rows"],
                $gridFitting["rotated"] ? "roteted" : "unrotated"
            );
            $actions = $prevActions;
            $actions[$gridFitting["explanation"]["machine"]["name"]] = $gridFitting;
            if (array_key_exists("prevActions", $gridFitting)) {
                $this->flattenActionPathTree($gridFitting["prevActions"][0], $actions);
            } else {
                $this->actionPaths[] = [
                    "actions" => $actions
                ];
//                echo(json_encode($this->actionPaths));
//                die();
            }
        }
    }


    protected function createExplanation($actionPath): JsonResponse
    {
//        return new JsonResponse(
//            $this->actionPath,
//            JsonResponse::HTTP_OK
//        );

        $equipments = $this->equipmentService->load();

        $actionPath = array_reverse($actionPath);
//        $actionPath = array_reverse($this->actionPath);
        $cutSheetCount = $this->config["number-of-copies"];

        $responseData = [];

        $actionIds = array_keys($actionPath);
        $lastActionIndex = count($actionIds) - 1;

        $prevMachineAction = null;

        $totalDuration = 0;
        $totalCost = 0;
        foreach ($actionPath as $machineId => $action) {

            $machineConfig = $this->findMachineConfigById($machineId, $this->config["machines"]);

            $actionIndex = array_search($machineId, $actionIds);

            $nextAction = null;
            if (array_key_exists($actionIndex + 1, $actionIds)) {
                $nextAction = $actionPath[$actionIds[$actionIndex + 1]];
            }

            $machineTypeToActionTypeMap = [
                "printing press" => "print",
                "folder" => "folding",
                "stitching machine" => "stitching",
            ];

            $actionType = $machineTypeToActionTypeMap[$action["options"]["type"]];

            $actionData = [
                "actionType" => $actionType,
                "machine" => $machineId,
                "minSheet" => [
                    "width" => $action["minSheet"]["width"],
                    "height" => $action["minSheet"]["height"],
                ],
                "maxSheet" => [
                    "width" => $action["maxSheet"]["width"],
                    "height" => $action["maxSheet"]["height"],
                ],
                "cutSheet" => [
                    "width" => $action["cutSheet"]["width"],
                    "height" => $action["cutSheet"]["height"],
                ],
                "inputSheet" => [
                    "width" => $action["pressSheet"]["width"],
                    "height" => $action["pressSheet"]["height"],
                ]
            ];

            if ($actionType === "print") {

                // Add CTP

                $sqm =
                    (
                        $action["pressSheet"]["width"]
                        *
                        $action["pressSheet"]["height"]
                    )
                    /
                    1000000
                ;

                $setupDuration = 0;
                $runDuration =
                    (
                        (
                            (
                                $sqm
                                *
                                1.05
                            )
                            /
                            $equipments["ctp-machine"]["sqm-per-hour"]
                        )
                        *
                        60
                        *
                        $this->config["number-of-colors"]
                    )
                ;

                $ctpCost =
                    (
                        $runDuration
                        /
                        60
                    )
                    *
                    $equipments["ctp-machine"]["cost-per-hour"]
                ;

                $responseData[] = [
                    "actionType" => "ctp",
                    "machine" => "ctp",
                    "minSheet" => [
                        "width" => $action["minSheet"]["width"],
                        "height" => $action["minSheet"]["height"],
                    ],
                    "maxSheet" => [
                        "width" => $action["maxSheet"]["width"],
                        "height" => $action["maxSheet"]["height"],
                    ],
                    "cutSheet" => [
                        "width" => $action["cutSheet"]["width"],
                        "height" => $action["cutSheet"]["height"],
                    ],
                    "inputSheet" => [
                        "width" => $action["pressSheet"]["width"],
                        "height" => $action["pressSheet"]["height"],
                    ],
                    "numberOfSheets" => $cutSheetCount,
                    "setupDuration" => round($setupDuration, 2),
                    "runDuration" => round($runDuration, 2),
                    "cost" => round($ctpCost, 2)
                ];
                $totalDuration += ($setupDuration + $runDuration);
                $totalCost += $ctpCost;

                $actionData["inputSheet"] = [
                    "width" => $action["pressSheet"]["width"],
                    "height" => $action["pressSheet"]["height"],
                ];

                // paper cost calculation
                $productsPerSheet = count($action["tiles"]);
                $paperCostPerProduct = round($action["pressSheet"]["price"] / $productsPerSheet, 2);
                $actionData["numberOfSheets"] = $cutSheetCount;
                $actionData["sheetPrice"] = $action["pressSheet"]["price"];
                $actionData["productsPerSheet"] = $productsPerSheet;
                $actionData["printingSheets"] = ceil($this->config["number-of-copies"] / $productsPerSheet);
                $actionData["paperCostPerProduct"] = $paperCostPerProduct;
                $actionData["printingPaperCost"] = $this->config["number-of-copies"] * $paperCostPerProduct;

                $totalCost += $actionData["printingPaperCost"];

                // setup duration calculation
                $numberOfColors = $this->config["number-of-colors"];
                $setupDuration =
                    $action["options"]["base-setup-duration"]
                    +
                    (
                        $numberOfColors
                        *
                        $action["options"]["setup-duration-per-color"]
                    )
                ;
                $actionData["setupDuration"] = round($setupDuration, 2);

                // Calculation of number of stack replenishments
                $numberOfStackReplenishments =
                    (
                        $actionData["printingSheets"]
                        *
                        (
                            $this->config["paper-weight"]
                            /
                            115
                        )
                        /
                        100
                    )
                    /
                    $machineConfig["max-input-stack-height"];

                // Calculation of run duration
                $runDuration =
                    (
                        $numberOfStackReplenishments
                        *
                        $machineConfig["stack-replenishment-duration"]
                    )
                    +
                    (
                        (
                            $actionData["printingSheets"]
                            /
                            $machineConfig["sheets-per-hour"]
                        )
                        *
                        60
                    )
                ;

                $actionData["runDuration"] = round($runDuration, 2);

                $duration = $actionData["setupDuration"] + $actionData["runDuration"];
                $printCost =
                    (
                        $duration
                        /
                        60
                    )
                    *
                    $action["options"]["cost-per-hour"]
                ;
                $actionData["cost"] = round($printCost, 2);
                $totalDuration += $duration;
                $totalCost += $printCost;


            } elseif ($actionType === "folding") {
                $actionData["inputSheet"] = $action["cutSheet"];

                $numberOfFolds = $machineConfig["number-of-folds"];
                $inputSheetLength = $this->config["openPose"]["height"] / 1000;

                $setupDuration =
                    $machineConfig["base-setup-duration"]
                    +
                    (
                        $numberOfFolds
                        *
                        $machineConfig["setup-duration-by-fold"]
                    )
                ;

                $runDuration =
                    (
                        (
                            (
                                $inputSheetLength
                                +
                                $machineConfig["document-spacing"]
                            )
                            *
                            $cutSheetCount
                        )
                        /
                        $machineConfig["meters-per-hour"]
                    )
                    *
                    60
                ;

                $duration = $setupDuration + $runDuration;
                $foldingCost = ($duration / 60) * $action["options"]["cost-per-hour"];

                $actionData["numberOfSheets"] = $cutSheetCount;
                $actionData["setupDuration"] = round($setupDuration, 2);
                $actionData["runDuration"] = round($runDuration, 2);
                $actionData["cost"] = round($foldingCost, 2);

                $totalDuration += $duration;
                $totalCost += $foldingCost;

            } elseif ($actionType === "stitching") {
                $setupDuration = $machineConfig["base-setup-duration"];
                $runDuration =
                    (
                        $cutSheetCount
                        /
                        $machineConfig["documents-per-hour"]
                    )
                    *
                    60
                ;
                $actionData["inputSheet"] = $action["cutSheet"];
                $actionData["numberOfSheets"] = $cutSheetCount;
                $actionData["setupDuration"] = round($setupDuration, 2);
                $actionData["runDuration"] = round($runDuration, 2);

                $foldingDuration = $setupDuration + $runDuration;
                $foldingCost =
                    (
                        $foldingDuration
                        /
                        60
                    )
                    *
                    $action["options"]["cost-per-hour"]
                ;
                $actionData["cost"] = round($foldingCost, 2);

                $totalDuration += $foldingDuration;
                $totalCost += $foldingCost;


            } else {
                $actionData["inputSheet"] = $action["cutSheet"];
//                $actionData["inputSheet"] = $prevMachineAction["cutSheet"];
            }

            $responseData[] = $actionData;


            $numberOfCuts = 0;
            /////////////////////
            // Add trimming
            /////////////////////
            if (
                ($nextAction !== null)
                &&
                (
                    ($nextAction["cutSheet"]["width"] !== $action["maxSheet"]["width"])
                    ||
                    ($nextAction["cutSheet"]["height"] !== $action["maxSheet"]["height"])
                )

            ) {
                $numberOfTrimCuts = 0;
                $numberOfTrimCuts += (($action["trimLines"]["top"]["y"] > 0) ? 2 : 0);
                $numberOfTrimCuts += (($action["trimLines"]["left"]["x"] > 0) ? 2 : 0);

            }

            /////////////////////
            // Add cutting
            /////////////////////

            $numberOfCutCuts = $action["cols"] - 1 + $action["rows"] - 1;

            $numberOfCuts = $numberOfTrimCuts + $numberOfCutCuts;

            if ($numberOfCuts > 0) {

                // calculate number of handfuls
                $numberOfHandfuls =
                    $cutSheetCount
                    *
                    (
                        $this->config["paper-weight"]
                        /
                        115
                    )
                    /
                    800
                ;
                $numberOfHandfuls = ceil($numberOfHandfuls);

                // calculate run duration
                $runDuration =
                    $numberOfHandfuls
                    *
                    (
                        (
                            $numberOfCutCuts
                            *
                            $equipments["cutting-machine"]["cut-duration"]
                        )
                        +
                        $equipments["cutting-machine"]["paper-circuit-duration"]
                    )
                ;
                $runDuration = round($runDuration, 2);

                $duration = round($runDuration + $equipments["cutting-machine"]["setup-duration"], 2);
                $cuttingCost =
                    (
                        $duration
                        /
                        60
                    )
                    *
                    $equipments["cutting-machine"]["cost-per-hour"]
                ;

                $responseData[] = [
                    "actionType" => "cut",
                    "machine" => "cutter",
                    "numberOfSheets" => $cutSheetCount,
                    "numberOfCuts" => $numberOfCuts,
                    "numberOfHandfuls" => $numberOfHandfuls,
                    "setupDuration" => $equipments["cutting-machine"]["setup-duration"],
                    "runDuration" => $runDuration,
                    "cost" => round($cuttingCost, 2)
                ];
                $totalDuration += ($equipments["cutting-machine"]["setup-duration"] + $runDuration);
                $totalCost += $cuttingCost;

                if ($numberOfCutCuts > 0) {
                    $cutSheetCount = $cutSheetCount * $action["cols"] * $action["rows"];
                }

            }

            if (
                $nextAction !== null
                &&
                $action["rotated"]
            ) {
                $responseData[] = [
                    "actionType" => "rotation",
//                    "machine" => "cutter",
//                    "numberOfCuts" => $numberOfCuts,
                ];
            }

            $prevMachineAction = $actionData;

        }

        $responseData = [
            "actions" => $responseData,
            "total" => [
                "totalDuration" => round($totalDuration, 2),
                "totalCost" => round($totalCost, 2),
            ]
        ];

        return new JsonResponse(
            $responseData,
            JsonResponse::HTTP_OK
        );

    }

    protected function findMachineConfigById(string $id, array $machines): ?array
    {
        foreach ($machines as $machine) {
            if ($machine["id"] === $id) {
                return $machine;
            }
        }
        return null;
    }

}