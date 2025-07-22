<?php

namespace App\Controller;

use App\Application\Joblang\ResponseTransformer\JoblangScriptParseResponseTransformer;
use App\Application\Joblang\UseCase\ParseJoblangScript\JoblangScriptParseResponseModel;
use App\Domain\Action\AbstractAction;
use App\Domain\Action\ActionType;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Domain\Equipment\Interfaces\EquipmentFactoryInterface;
use App\Domain\Equipment\Interfaces\EquipmentServiceInterface;
use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Geometry\Dimensions;
use App\Domain\Layout\Calculator;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Domain\Sheet\PrintFactory;
use App\Entity\JoblangScript;
use App\Infrastructure\Mapper\JobMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class TestController extends AbstractController
{
    protected MachineInterface $machine;
    protected array $pressSheets;
    protected InputSheetInterface $zone;
    protected array $pose;

    public function __construct(
        protected Calculator $layoutCalculator,
        protected PrintFactory $printFactory,
        protected EquipmentServiceInterface $equipmentService,
        protected EquipmentFactoryInterface $equipmentFactory,
        protected ActionTreeInterface $actionTree,
        protected KernelInterface $kernel,
    )
    {
    }

    #[Route(path: '/test/{scriptId}', requirements: [], methods: ['GET'])]
    public function getTest(
        string $scriptId,
        EntityManagerInterface $em,
        JobMapper $jobMapper,
        JoblangScriptParseResponseTransformer $responseTransformer,
    ): JsonResponse
    {
        ini_set('memory_limit', '512M');


        $repo = $em->getRepository(JoblangScript::class);
        $joblangScript = $repo->findWithParts($scriptId);

        $responses = [];

        foreach ($joblangScript->getLines() as $line) {
            $jobEntity = $line->getJob(); // Doctrine entity

            $domainJob = $jobMapper->toDomain($jobEntity); // Convert to domain Job object
            $parts = $domainJob->getParts();

            $metaData = $jobEntity->getMetaData();

            $responseModel = new JoblangScriptParseResponseModel(
                $joblangScript->getId(),
                $domainJob,
                $parts,
                $metaData
            );

            $response = $responseTransformer->transform($responseModel);

            // press sheet
            $this->pressSheets = [
//                $this->printFactory->newPressSheet(
//                    "pressSheet",
//                    0,
//                    0,
//                    1020,
//                    700,
//                    2
//                ),
//                $this->printFactory->newPressSheet(
//                    "pressSheet",
//                    0,
//                    0,
//                    1000,
//                    700,
//                    2
//                )

//                1020x720 -> 202.69€
//                450x320 -> 198.72

                $this->printFactory->newPressSheet(
                    "pressSheet",
                    0,
                    0,
                    1020,
                    720,
                    $this->calculateSheetPrice(1150, 120, 720, 1020)
                ),
                $this->printFactory->newPressSheet(
                    "pressSheet",
                    0,
                    0,
                    880,
                    640,
                    $this->calculateSheetPrice(1150, 120, 640, 880)
                ),
                $this->printFactory->newPressSheet(
                    "pressSheet",
                    0,
                    0,
                    740,
                    540,
                    $this->calculateSheetPrice(1150, 120, 540, 740)
                ),
                $this->printFactory->newPressSheet(
                    "pressSheet",
                    0,
                    0,
                    450,
                    320,
                    $this->calculateSheetPrice(1150, 120, 320, 450)
                ),
            ];


            $payload = $this->processPayload($response);

            $parts = [];
            foreach ($payload["parts"] as $part) {

                $actionPaths = $this->actionTree->process(
                    $part["abstractActions"],
                    $part["pressSheets"],
//                $part["pressSheet"],
                    $part["zone"],
                    $part["openPoseDimensions"],
                    $part["numberOfCopies"],
                    $part["numberOfColors"],
                    $part["paperWeight"],
                    $part["medium"]["inking"],
                );

                $parts[] = [
                    "partId" => $part["partId"],
                    "medium" => $part["medium"],
                    "openPoseDimensions" => $part["openPoseDimensions"],
                    "closedPoseDimensions" => $part["closedPoseDimensions"],
                    "actionPaths" => $actionPaths
                ];
            }

            $responses[] = $this->createResponse($parts);
//        $response = $this->createResponse($parts);

            $this->writeMetaData();
        }

        return new JsonResponse(
            $responses,
            JsonResponse::HTTP_OK
        );

//        return $responses;
//        return $response;
    }

    protected function processPayload($data): array
    {
//        $request = Request::createFromGlobals();
//        $data = json_decode($request->getContent(), true);

        $this->metaData = $data["metaData"];

        $payload = [
            "parts" => []
        ];

        foreach ($data["parts"] as $part) {
            $payload["parts"][] = $this->processPartPayload($part);
        }

        return $payload;
    }

    protected function processPartPayload($partPayload): array
    {

        $partId = $partPayload["partId"];

        $abstractActionData = $partPayload['actions'];
        $abstractActions = [];
        foreach ($abstractActionData as ["type" => $actionTypeName]) {
            $abstractActions[]= new AbstractAction(
                ActionType::tryFrom($actionTypeName),
                $this->equipmentFactory
            );
        }

        $numberOfCopies = $partPayload['numberOfCopies'];
        $paperWeight = floatval($partPayload["medium"]["weight"]);
        $numberOfColors = count($partPayload["medium"]["inking"]["recto"]);

        [$open, $closed] = explode('/', $partPayload["size"]);
        [$closedWidth, $closedHeight] = explode('x', $closed);
        [$openWidth, $openHeight] = explode('x', $open);
        $size = [
            'closed' => [
                'width' => (float) $closedWidth,
                'height' => (float) $closedHeight,
            ],
            'open' => [
                'width' => (float) $openWidth,
                'height' => (float) $openHeight,
            ],
        ];

        $openPoseDimensions = new Dimensions(
            $size["open"]["width"],
            $size["open"]["height"]
        );

        $closedPoseDimensions = new Dimensions(
            $size["closed"]["width"],
            $size["closed"]["height"]
        );

        $this->pose = $size["closed"];

        // set zone
        $zoneWidth = $partPayload["zone"]["width"];
        $zoneHeight = $partPayload["zone"]["height"];

//        if ($this->machine->getType()->value === "folder") {
//            $zoneWidth = $data["parts][[0]"openPose"]["width"];
//            $zoneHeight = $data["parts][[0]"openPose"]["height"];
//        }

        $zone = $this->printFactory->newInputSheet( // perhaps better to handle it as a Tile?
            "zone",
            0,
            0,
            $zoneWidth,
            $zoneHeight,
        );
        $zone->setGripMarginSize($partPayload["zone"]["gripMargin"]["size"]);
        $zone->setContentType($partPayload["zone"]["type"]); // todo: make it better

        return [
            "abstractActions" => $abstractActions,
//            "pressSheets" => $pressSheets,
            "pressSheets" => $this->pressSheets,
//            "pressSheet" => $this->pressSheets[0],
            "zone" => $zone,
            "openPoseDimensions" => $openPoseDimensions,
            "closedPoseDimensions" => $closedPoseDimensions,
            "numberOfCopies" => $numberOfCopies,
            "numberOfColors" => $numberOfColors,
            "paperWeight" => $paperWeight,
            "partId" => $partId,
            "medium" => $partPayload["medium"]
        ];

    }

    protected function createResponse($parts): array
    {
        $responseData = [
            "metaData" => $this->metaData,
            "parts" => []
        ];

        foreach ($this->getActionPaths($parts) as $loop => $actionPaths) {
            $partId = $parts[$loop]["partId"];
            $responseData["parts"][$partId]["actionPaths"] = [];
            $usedCosts = [];
            foreach ($actionPaths as $loop2 => $actionPath) {
                if (!in_array($actionPath["cost"], $usedCosts)) {
                    $responseData["parts"][$partId]["actionPaths"][] = $actionPath;
                    $usedCosts[] = $actionPath["cost"];
                    $usedCosts = array_values(array_unique($usedCosts));
                }

                if (count($usedCosts) >= 10) {
                    break;
                }

            }

        }

        return $responseData;
    }

    public function getActionPathNodes($actionPath)
    {
        foreach ($actionPath as $action) {
            $actionArray = $action->toArray($action->getMachine(), $action->getPressSheet(), $this->pose);
            yield $actionArray;
        }
    }

    public function getActionPath($part, $maxTileCountPerSqm)
//    public function getActionPath($part, $maxTileCount)
    {

        foreach ($part["actionPaths"] as $actionPath) {

            $sheetSqm = ($actionPath[0]->getPressSheet()->getWidth() * $actionPath[0]->getPressSheet()->getHeight()) / 1000000;
            $tileCount = $actionPath[0]->getGridFitting()->getCols() * $actionPath[0]->getGridFitting()->getRows();
            $tileCountPerSqm = $tileCount / $sheetSqm;

            if ($tileCountPerSqm < $maxTileCountPerSqm -2) {
                continue;
            }
//            if ($tileCount < $maxTileCount) {
//                continue;
//            }

            $path = [
                "designation" => [],
                "nodes" => []
            ];
            $cost = 0;
            $duration = 0;

            foreach ($this->getActionPathNodes($actionPath) as $node) {
                $x = 0;
                if (is_array($node["cost"])) {
                    foreach ($node["cost"] as $costName => $additionalCost) {
                        if ($costName === "cost") {
                            continue;
                        }
                        $x += $additionalCost;
                        $path[$costName] = $node["cost"][$costName];

                    }
                    $node["cost"] = $node["cost"]["cost"];
                }
                $cost += $node["cost"];

                $duration += ($node["setupDuration"] + $node["runDuration"]);
                $path["designation"][] = $node["machine"];
                $path["nodes"][] = $node;
            }

            if (array_key_exists("paperCost", $path)) {
                $cost += $path["paperCost"];
            }
            if (array_key_exists("aluSheetsCost", $path)) {
                $cost += $path["aluSheetsCost"];
            }

            $path["designation"] = implode(" > ", $path["designation"]);
            $path["designation"] .= sprintf(" Cost: %s€; Duration: %smin", $cost, $duration);
            $pressSheetText = sprintf("%dx%d", $path["nodes"][0]["pressSheet"]["width"], $path["nodes"][0]["pressSheet"]["height"]);
            $path["designation"] = sprintf("(%s) %s", $pressSheetText, $path["designation"]);



            $path["medium"] = $part["medium"];
            $path["openPoseDimensions"] = sprintf(
                "%dx%d",
                $part["openPoseDimensions"]->getWidth(),
                $part["openPoseDimensions"]->getHeight()
            );
            $path["closedPoseDimensions"] = sprintf(
                "%dx%d",
                $part["closedPoseDimensions"]->getWidth(),
                $part["closedPoseDimensions"]->getHeight()
            );
            $path["cost"] = $cost;
            $path["duration"] = $duration;
            $path["pressSheet"] = sprintf("%smm", $pressSheetText);
            $path["md5"] = md5(serialize($path));
            $path["id"] = Uuid::v4()->toString();
            yield $path;
        }

    }

    public function getActionPaths($parts)
    {
        foreach ($parts as $part) {

            $actionPathArray = [];

            $maxTileCountPerSqm = 0;
            $maxTileCount = 0;
            foreach ($part["actionPaths"] as $actionPath) {

                $tileCount = $actionPath[0]->getGridFitting()->getCols() * $actionPath[0]->getGridFitting()->getRows();
                $sheetSqm = ($actionPath[0]->getPressSheet()->getWidth() * $actionPath[0]->getPressSheet()->getHeight()) / 1000000;

                $tileCountPerSqm = $tileCount / $sheetSqm;

                if ($tileCount > $maxTileCount) {
                    $maxTileCount = $tileCount;
                }

                if ($tileCountPerSqm > $maxTileCountPerSqm) {
                    $maxTileCountPerSqm = $tileCountPerSqm;
                }
            }


            foreach ($this->getActionPath($part, $maxTileCountPerSqm) as $actionPath) {
                $actionPathArray[] = $actionPath;
            }

            usort($actionPathArray, function ($a, $b) {
                if ($a["cost"] === $b["cost"]) {
                    return $a["duration"] >= $b["duration"];
                } else {
                    return $a["cost"] >= $b["cost"];
                }
            });

            $directoryPath = sprintf("%s/data/%s", realpath($this->kernel->getProjectDir()), $this->metaData["jobNumber"]);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
                mkdir(sprintf("%s/parts", $directoryPath), 0755, true);
                mkdir(sprintf("%s/meta", $directoryPath), 0755, true);
            }

            $filePath = sprintf("%s/parts/%s.json", $directoryPath, $part["partId"]);
            file_put_contents($filePath, json_encode($actionPathArray, JSON_PRETTY_PRINT));

            yield $actionPathArray;
        }

    }

    public function writeMetaData()
    {
        $filePath = sprintf(
            "%s/data/%s/meta/metaData.json",
            realpath($this->kernel->getProjectDir()),
            $this->metaData["jobNumber"]
        );

        file_put_contents($filePath, json_encode($this->metaData, JSON_PRETTY_PRINT));

    }

    protected function calculateSheetPrice($mediumPricePerTon, $mediumWeightPerSqm, $mediumHeight, $mediumWidth)
    {
        $mediumPricePerGram = $mediumPricePerTon / (1000 * 1000);
        $mediumSqm = ($mediumHeight * $mediumWidth) / 1000000;
        $mediumWeight = $mediumWeightPerSqm * $mediumSqm;
        $mediumPricePerSheet = $mediumWeight * $mediumPricePerGram;

        return $mediumPricePerSheet;

    }

}