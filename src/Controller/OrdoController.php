<?php

namespace App\Controller;

use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Equipment\Machine;
use App\Domain\Equipment\MachineType;
use App\Domain\Geometry\Dimensions;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Layout\Calculator;
use App\Domain\Layout\Interfaces\GridFittingInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\PrintFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class OrdoController extends AbstractController
{
    protected array $payload;

    public function __construct(
        protected KernelInterface $kernel,
        protected SluggerInterface $slugger,
        protected PropertyAccessorInterface $propertyAccessor,
        protected EquipmentFactoryInterface $equipmentFactory,
    )
    {
    }

    #[Route(path: '/ordo', requirements: [], methods: ['POST'])]
    public function ordo(
    ): JsonResponse
    {

        $this->processPayload();

        $deadline = $this->payload["metaData"]["deadline"];
        $deadline = substr($deadline, 1);
        $deadline = explode(" ", $deadline);

        $deadlineOffset = array_pop($deadline);
        $deadline = implode(" ", $deadline);
        $deadline = str_replace("/", "-", $deadline);

        $ordoPayload = [
            "id" => $this->payload["metaData"]["jobNumber"],
            "designation" => "Brochure Alternance - 12+4 - A4 - pelli brillant - 2500ex", // this is probably the meta part of the joblang
            "technique"  => [ // "technique" here is redundant, and it varies in parts
                "type_de_papier"  => "couché brillant",
                "grammage"  => 250,
                "format_feuillesr"  => "70x102",
                "format_ouvert"  => "A4",
                "format_fermé"  => "A5",
                "pagination/volets"  => 96
            ],
            "client" => $this->payload["metaData"]["client"],
            "deadline" => $deadline, // format: "20/09/2024 13h00",
            "deadline_imperative" => true, // needs parse change to extract it, perhaps an exclamation mark means it
            "deadline_BAT" => "15/09/2024 13h00", // ask about it
            "BAT" => true, // ask about it
            "required_jobs" => [], // this is not implemented yet
            "parts" => []
        ];

        foreach ($this->payload["paths"] as $partId => $path) {
            $mediumType = null;
            $mediumProps = $this->propertyAccessor->getValue($path, "[medium][name]");
            if (is_array($mediumProps)) {
                $mediumType = implode(" ", $mediumProps);
            }

            $mediumWeight = $this->propertyAccessor->getValue($path, "[medium][weight]");

//            dump($this->propertyAccessor->getValue($path, "[medium][weight]"));
//            dump($path);
//            die();

            $part = [
                "part_id" => $partId,
                "designation" => "cahier 8 pages", // it is not present in the joblang
                "technique"  => [
                    "type_de_papier"  => $mediumType,
                    "grammage"  => floatval($mediumWeight),
                    "format_feuillesr"  => "70x102", // what are the different formats? open, closed, etc?
                    "format_ouvert"  => $path["openPoseDimensions"], // "A4",
                    "format_fermé"  => $path["closedPoseDimensions"], // "A5",
                    "pagination/volets"  => 96
                ],
                "actions" => []
            ];

            foreach ($path["nodes"] as $loop => $node) {

                $machine = $this->equipmentFactory->fromId($node["machine"]);

                if ($machine->getType() === MachineType::PrintingPress) {
                    $dryTimeBetweenSequences = $this->propertyAccessor->getValue($node, "[todo][dryTimeBetweenSequences]");
                    if ($dryTimeBetweenSequences !== null) {
                        $part["actions"][count($part["actions"])-1]["sequences"] = ["recto", "verso"];
//                        $part["actions"][count($part["actions"])-1]["sequences"] = 2;
                        $part["actions"][count($part["actions"])-1]["dry_time_between_sequences"] = $dryTimeBetweenSequences;
                        continue;
                    }
                }

                $action = [
                    "machine" => $node["machine"],
//                    "setup" => round($node["setupDuration"]),
                    "setup_time" => round($node["setupDuration"]),
                    "run" => round($node["runDuration"]),
                ];
                $part["actions"][] = $action;
            }

            $ordoPayload["parts"][] = $part;
        }

        $this->uploadFtpFiles($ordoPayload);

        $response = [
            "ordo" => $ordoPayload,
            "payload" => $this->payload,
        ];

//        dump($ordoPayload);
//        dump($this->payload);
//        die();


        return new JsonResponse(
            $response,
            JsonResponse::HTTP_OK
        );
    }

    protected function processPayload(): void
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $metaData = $this->readMetaData($data["jobId"]);

        $this->payload = [
            "metaData" => $metaData,
            "paths" => $this->loadSelectedPaths($data)
        ];
    }

    protected function loadSelectedPaths($payload): array
    {
        $selectedPaths = [];
        foreach ($payload["selectedUuids"] as $part) {
            $path = sprintf(
                "%s/data/%s/parts/%s.json",
                $this->kernel->getProjectDir(),
                $payload["jobId"],
                $part["partId"]
            );

            $actionPathUuid = $part["value"];

            $partData = file_get_contents($path);
            $partData = json_decode($partData, true);

            $selectedPath = array_filter($partData, function ($item) use ($actionPathUuid) {
                return $actionPathUuid === $item["id"];
            });
            $selectedPath = array_values($selectedPath)[0];

            $selectedPaths[$part["partId"]] = $selectedPath;
        }

        return $selectedPaths;
    }

    protected function createResponse(): JsonResponse
    {
//        return new JsonResponse(
//            $this->actionPath,
//            JsonResponse::HTTP_OK
//        );

        $equipments = $this->equipmentService->load();

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
                            $numberOfCuts
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

                if ($numberOfCuts > 0) {
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

    protected function readMetaData($jobId)
    {
        $path = sprintf("%s/data/%s/meta/metaData.json", $this->kernel->getProjectDir(), $jobId);
        $metaData = file_get_contents($path);
        return json_decode($metaData, true);
    }

    protected function uploadFtpFiles($job)
    {
        $filesystem = new Filesystem(new SftpAdapter(
            new SftpConnectionProvider(
                '135.125.13.215', // host (required)
                'ordo-manager', // username (required)
                'Kj87mP9vL2snR5x', // password (optional, default: null) set to null if privateKey is used
                null, // private key (optional, default: null) can be used instead of password, set to null if password is set
                null, // passphrase (optional, default: null), set to null if privateKey is not used or has no passphrase
                2222, // port (optional, default: 22)
                false, // use agent (optional, default: false)
                30, // timeout (optional, default: 10)
                10, // max tries (optional, default: 4)
                null, // host fingerprint (optional, default: null),
                null, // connectivity checker (must be an implementation of 'League\Flysystem\PhpseclibV2\ConnectivityChecker' to check if a connection can be established (optional, omit if you don't need some special handling for setting reliable connections)
            ),
            '/in', // root path (required)
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => 0640,
                    'private' => 0604,
                ],
                'dir' => [
                    'public' => 0740,
                    'private' => 7604,
                ],
            ])
        ));

        $machineIds = [];
        foreach ($job["parts"] as $part) {
            foreach ($part["actions"] as $action) {
                $machineIds[] = $action["machine"];
            }
        }
        $machineIds = array_unique($machineIds);

        $machines = [];
        foreach ($machineIds as $machineId) {
            $machines[] = $this->equipmentFactory->fromId($machineId);
        }

        foreach ($machines as $machine) {
            $machineConfigurationFilePath = sprintf("machines/%s.json", $this->slugger->slug($machine->getId()));
            $filesystem->write($machineConfigurationFilePath, json_encode($machine->getOrdoData(), JSON_PRETTY_PRINT));
        }

        $dryingMachine = <<<DRY
{
  "id": "sechage",
  "designation": "séchage",
  "designation_technique": "séchage",
  "type": "temps incompressible",
  "capacite": 9999,
  "peremption_calage": 999999,
  "regime_nominal": {
    "attention_requise": 0,
    "productivite": 1
  }
}
DRY;

        $filesystem->write('machines/sechage.json', $dryingMachine);


        $alain = <<<ALAIN
{
  "identite": {
    "trigramme": "alo",
    "nom": "Louis",
    "prenom": "Alain",
    "poste": "conducteur offset"
  },
  "competences": {
    "bordeuse": 0.9,
    "conditionnement": 0.9,
    "expedition": 0.9,
    "gto": 0,
    "komori": 1,
    "mbo5070": 0,
    "mbo6592": 0,
    "mbo70100": 0,
    "meche_creuse": 0.66,
    "paqueteuse": 0.9,
    "pelliculeuse": 0.9,
    "piqueuse_manuelle": 0.66,
    "polar": 0,
    "raineuse_manuelle": 0.66,
    "ryobi": 0,
    "sbg": 0,
    "wireo_perfo": 0.66,
    "wireo_reliure": 0.66,
    "wireo_serrage": 0.66,
    "Hohner": 1,
    "MBO XL": 1,
    "Komori G40": 1,
    "Komori G50": 1,
    "cutting-machine": 1,
    "ctp-machine": 1
  },
  "horaires": {
    "semaine_debut": 33,
    "services": [
      {
        "nom_du_service": "matin",
        "lundi": [
          "06:00-13:00"
        ],
        "mardi": [
          "06:00-13:00"
        ],
        "mercredi": [
          "06:00-13:00"
        ],
        "jeudi": [
          "06:00-13:00"
        ],
        "vendredi": [
          "06:00-13:00"
        ],
        "samedi": [],
        "dimanche": []
      },
      {
        "nom_du_service": "après-midi",
        "lundi": [
          "13:00-20h00"
        ],
        "mardi": [
          "13:00-20h00"
        ],
        "mercredi": [
          "13:00-20h00"
        ],
        "jeudi": [
          "13:00-20h00"
        ],
        "vendredi": [
          "13:00-20h00"
        ],
        "samedi": [],
        "dimanche": []
      },
      {
        "nom_du_service": "soir",
        "lundi": [
          "20:00-03:00"
        ],
        "mardi": [
          "20:00-03:00"
        ],
        "mercredi": [
          "20:00-03:00"
        ],
        "jeudi": [
          "20:00-03:00"
        ],
        "vendredi": [
          "20:00-03:00"
        ],
        "samedi": [],
        "dimanche": []
      }
    ]
  },
  "conges": [
    {
      "debut": "2025-12-24 00:00",
      "fin": "2025-12-30 23:59"
    }
  ]
}
ALAIN;


        $pierre = <<<PIERRE
{
  "identite": {
    "trigramme": "pid",
    "nom": "Pierre",
    "prenom": "Dubois",
    "poste": "conducteur offset"
  },
  "competences": {
    "bordeuse": 0.9,
    "conditionnement": 0.9,
    "expedition": 0.9,
    "gto": 0,
    "komori": 1,
    "mbo5070": 0,
    "mbo6592": 0,
    "mbo70100": 0,
    "meche_creuse": 0.66,
    "paqueteuse": 0.9,
    "pelliculeuse": 0.9,
    "piqueuse_manuelle": 0.66,
    "polar": 0,
    "raineuse_manuelle": 0.66,
    "ryobi": 0,
    "sbg": 0,
    "wireo_perfo": 0.66,
    "wireo_reliure": 0.66,
    "wireo_serrage": 0.66,
    "Hohner": 1,
    "MBO XL": 1,
    "Komori G40": 1,
    "Komori G50": 1,
    "cutting-machine": 1,
    "ctp-machine": 1
  },
  "horaires": {
    "semaine_debut": 32,
    "services": [
      {
        "nom_du_service": "matin",
        "lundi": [
          "06:00-13:00"
        ],
        "mardi": [
          "06:00-13:00"
        ],
        "mercredi": [
          "06:00-13:00"
        ],
        "jeudi": [
          "06:00-13:00"
        ],
        "vendredi": [
          "06:00-13:00"
        ],
        "samedi": [],
        "dimanche": []
      },
      {
        "nom_du_service": "après-midi",
        "lundi": [
          "13:00-20h00"
        ],
        "mardi": [
          "13:00-20h00"
        ],
        "mercredi": [
          "13:00-20h00"
        ],
        "jeudi": [
          "13:00-20h00"
        ],
        "vendredi": [
          "13:00-20h00"
        ],
        "samedi": [],
        "dimanche": []
      },
      {
        "nom_du_service": "soir",
        "lundi": [
          "20:00-03:00"
        ],
        "mardi": [
          "20:00-03:00"
        ],
        "mercredi": [
          "20:00-03:00"
        ],
        "jeudi": [
          "20:00-03:00"
        ],
        "vendredi": [
          "20:00-03:00"
        ],
        "samedi": [],
        "dimanche": []
      }
    ]
  },
  "conges": [
    {
      "debut": "2025-12-24 00:00",
      "fin": "2025-12-30 23:59"
    }
  ]
}
PIERRE;

        $filesystem->write('rh/alain.json', $alain);
        $filesystem->write('rh/pierre.json', $pierre);

        $filesystem->write(sprintf('jobs/%s.json', $job["id"]), json_encode($job, JSON_PRETTY_PRINT));

    }

}