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
    protected array $abstractActionData;

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


        $abstractActions = [];
        foreach ($this->abstractActionData as ["type" => $actionTypeName]) {
            $abstractActions[]= new AbstractAction(
                ActionType::tryFrom($actionTypeName),
                $this->equipmentFactory
            );
        }

        $actionPaths = $this->actionTree->process(
            $abstractActions,
            $this->pressSheet,
            $this->zone,
            $this->openPoseDimensions,
            $this->numberOfCopies,
            $this->numberOfColors,
            $this->paperWeight
        );

        $jobId = Uuid::v4()->toString();

        return $this->createResponse($actionPaths, $jobId);

    }

    protected function processPayload(): void
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $this->jobId = $data['jobId'];
        $this->abstractActionData = $data['actions'];

//        // machine
//        $this->machine = $this->equipmentFactory->fromId($data["machine"]["id"]);
//
//        // set openPoseDimensions
//        $this->machine->setOpenPoseDimensions(new Dimensions(
//            $data["openPose"]["width"],
//            $data["openPose"]["height"]
//        ));

        // set zone
        $zoneWidth = $data["zone"]["width"];
        $zoneHeight = $data["zone"]["height"];

//        if ($this->machine->getType()->value === "folder") {
//            $zoneWidth = $data["openPose"]["width"];
//            $zoneHeight = $data["openPose"]["height"];
//        }

        $this->zone = $this->printFactory->newInputSheet( // perhaps better to handle it as a Tile?
            "zone",
            0,
            0,
            $zoneWidth,
            $zoneHeight,
        );
        $this->zone->setGripMarginSize($data["zone"]["gripMargin"]["size"]);
        $this->zone->setContentType($data["zone"]["type"]); // todo: make it better

//        $this->actionPath = $data["action-path"];
        $this->pose = $data["pose"];
        $this->openPose = $data["openPose"];
    }

    protected function createResponse($actionPaths, $jobId): JsonResponse
    {
        $request = Request::createFromGlobals();
        $payload = json_decode($request->getContent(), true);

        $responseData = [];

        foreach ($actionPaths as $actionPath) {
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
            $responseData[] = $path;
        }

        usort($responseData, function ($a, $b) {
            if ($a["cost"] === $b["cost"]) {
                return $a["duration"] >= $b["duration"];
            } else {
                return $a["cost"] >= $b["cost"];
            }
        });

//        dump($responseData);
//        die();


        $filePath = sprintf("%s/data/%s.json", realpath($this->kernel->getProjectDir()), $this->jobId);
//        echo($filePath);
//        echo(json_encode($responseData, JSON_PRETTY_PRINT));
        file_put_contents($filePath, json_encode($responseData, JSON_PRETTY_PRINT));
//        die();

        return new JsonResponse(
            $responseData,
            JsonResponse::HTTP_OK
        );

    }

}