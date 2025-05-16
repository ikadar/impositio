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
    )
    {
    }

    #[Route(path: '/test', requirements: [], methods: ['POST'])]
    public function getTest(
    ): JsonResponse
    {
        $this->processPayload();

        $gridFittings = $this->layoutCalculator->calculateGridFittings(
            $this->machine,
            $this->pressSheet,
            $this->zone, // tile
        );

        return $this->createResponse($gridFittings, $this->actionPath);
    }

    protected function processPayload(): void
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

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
}