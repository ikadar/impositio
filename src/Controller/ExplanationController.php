<?php

namespace App\Controller;

use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\Machine;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\PrintFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ExplanationController extends AbstractController
{
    protected array $actionPath;
    protected array $config;

    public function __construct(
        protected Calculator $layoutCalculator,
        protected PrintFactory $printFactory,
    )
    {
    }

    #[Route(path: '/explanation', requirements: [], methods: ['POST'])]
    public function getTest(
    ): JsonResponse
    {
        $this->processPayload();

        return $this->createResponse();
    }

    protected function processPayload(): void
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $this->actionPath = $data["action-path"];
        $this->config = $data["config"];
    }

    protected function createResponse(): JsonResponse
    {
//        return new JsonResponse(
//            $this->actionPath,
//            JsonResponse::HTTP_OK
//        );

        $actionPath = array_reverse($this->actionPath);
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
                            $this->config["ctp-machine"]["sqm-per-hour"]
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
                    $this->config["ctp-machine"]["cost-per-hour"]
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
                            $this->config["cutting-machine"]["cut-duration"]
                        )
                        +
                        $this->config["cutting-machine"]["paper-circuit-duration"]
                    )
                ;
                $runDuration = round($runDuration, 2);

                $duration = round($runDuration + $this->config["cutting-machine"]["setup-duration"], 2);
                $cuttingCost =
                    (
                        $duration
                        /
                        60
                    )
                    *
                    $this->config["cutting-machine"]["cost-per-hour"]
                ;

                $responseData[] = [
                    "actionType" => "cut",
                    "machine" => "cutter",
                    "numberOfSheets" => $cutSheetCount,
                    "numberOfCuts" => $numberOfCuts,
                    "numberOfHandfuls" => $numberOfHandfuls,
                    "setupDuration" => $this->config["cutting-machine"]["setup-duration"],
                    "runDuration" => $runDuration,
                    "cost" => round($cuttingCost, 2)
                ];
                $totalDuration += ($this->config["cutting-machine"]["setup-duration"] + $runDuration);
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