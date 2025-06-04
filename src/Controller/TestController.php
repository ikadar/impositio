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
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class TestController extends AbstractController
{
    protected MachineInterface $machine;
    protected RectangleInterface $pressSheet;
    protected InputSheetInterface $zone;
    protected array $actionPath;
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

    #[Route(path: '/test', requirements: [], methods: ['POST'])]
    public function getTest(
    ): JsonResponse
    {
        // press sheet
        $this->pressSheet = $this->printFactory->newPressSheet(
            "pressSheet",
            0,
            0,
            1020,
            700,
            1
        );

        $payload = $this->processPayload();

        $parts = [];
        foreach ($payload["parts"] as $part) {
            $actionPaths = $this->actionTree->process(
                $part["abstractActions"],
                $part["pressSheet"],
                $part["zone"],
                $part["openPoseDimensions"],
                $part["numberOfCopies"],
                $part["numberOfColors"],
                $part["paperWeight"],
            );
            $parts[] = [
                "partId" => $part["partId"],
                "actionPaths" => $actionPaths
            ];
        }

        return $this->createResponse($parts);
//        return $this->createResponse($actionPaths, $payload["parts"][0]["partId"]);
    }

    protected function processPayload(): array
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $this->jobId = $data["jobId"];

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
            "pressSheet" => $this->pressSheet,
            "zone" => $zone,
            "openPoseDimensions" => $openPoseDimensions,
            "numberOfCopies" => $numberOfCopies,
            "numberOfColors" => $numberOfColors,
            "paperWeight" => $paperWeight,
            "partId" => $partId,
        ];

    }

    protected function createResponse($parts): JsonResponse
//    protected function createResponse($actionPaths, $partId): JsonResponse
    {
        $responseData = [
            "jobId" => $this->jobId,
            "parts" => []
        ];

        foreach ($parts as $part) {
            $actionPathArray = [];
            foreach ($part["actionPaths"] as $actionPath) {
                $path = [
                    "designation" => [],
                    "nodes" => []
                ];
                $cost = 0;
                $duration = 0;
                foreach ($actionPath as $action) {

                    $actionArray = $action->toArray($action->getMachine(), $this->pressSheet, $this->pose);
                    $cost += $actionArray["cost"];
                    $duration += ($actionArray["setupDuration"] + $actionArray["runDuration"]);
                    $path["designation"][] = $actionArray["machine"];

//                $path["designation"] .= sprintf(
//                    "- %s (%dx%d %s)<br/>",
//                    $actionArray["machine"],
//                    $actionArray["gridFitting"]["cols"],
//                    $actionArray["gridFitting"]["rows"],
//                    $actionArray["gridFitting"]["rotated"] ? "rotated" : "unrotated"
//                );
                    $path["nodes"][] = $actionArray;
                }
                $path["id"] = Uuid::v4()->toString();
                $path["designation"] = implode(" > ", $path["designation"]);
                $path["designation"] .= sprintf(" Cost: %s€; Duration: %smin", $cost, $duration);
                $path["cost"] = $cost;
                $path["duration"] = $duration;
                $actionPathArray[] = $path;
            }

            usort($actionPathArray, function ($a, $b) {
//            usort($responseData, function ($a, $b) {
                if ($a["cost"] === $b["cost"]) {
                    return $a["duration"] >= $b["duration"];
                } else {
                    return $a["cost"] >= $b["cost"];
                }
            });

            $directoryPath = sprintf("%s/data/%s", realpath($this->kernel->getProjectDir()), $this->jobId);
            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }

            $filePath = sprintf("%s/%s.json", $directoryPath, $part["partId"]);
            file_put_contents($filePath, json_encode($actionPathArray, JSON_PRETTY_PRINT));

            $responseData["parts"][$part["partId"]]["actionPaths"] = $actionPathArray;
        }


        return new JsonResponse(
            $responseData,
//            [
//                "jobId" => $this->jobId,
//                "parts" => [
//                    $partId
//                ],
//                "paths" => $responseData,
//            ],
            JsonResponse::HTTP_OK
        );

    }

}