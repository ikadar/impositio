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

class TestController extends AbstractController
{
    protected MachineInterface $machine;
    protected RectangleInterface $pressSheet;
    protected InputSheetInterface $zone;
    protected array $actionPath;

    public function __construct(
        protected Calculator $layoutCalculator,
        protected PrintFactory $printFactory,
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

        $this->machine = new Machine(
            $data["machine"]["id"],
            $data["machine"]["gripMargin"],
            new Dimensions(
                $data["machine"]["input-dimensions"]["min"]["width"],
                $data["machine"]["input-dimensions"]["min"]["height"]
            ),
            new Dimensions(
                $data["machine"]["input-dimensions"]["max"]["width"],
                $data["machine"]["input-dimensions"]["max"]["height"]
            ),
            $this->printFactory
        );

        $this->pressSheet = $this->printFactory->newRectangle(
            "pressSheet",
            0,
            0,
            $data["press-sheet"]["width"],
            $data["press-sheet"]["height"]
        );

//        $this->zone = $this->printFactory->newZone(
//        $this->zone = $this->printFactory->newInputSheet(
        $this->zone = $this->printFactory->newInputSheet( // perhaps better to handle it as a Tile?
            "zone",
            0,
            0,
            $data["zone"]["width"],
            $data["zone"]["height"]
        );
        $this->zone->setGripMarginSize($data["zone"]["gripMargin"]["size"]);
        $this->zone->setContentType($data["zone"]["type"]); // todo: make it better

        $this->actionPath = $data["action-path"];
    }

    protected function createResponse($gridFittings, $actionPath): JsonResponse
    {
        $responseData = [];
        /**
         * @var GridFittingInterface $gridFitting
         */
        foreach ($gridFittings as $gridFitting) {
            $data = $gridFitting->toArray($this->machine, $this->pressSheet);
            $data["actionPath"] = $actionPath;
            $responseData[] = $data;
        }

        return new JsonResponse(
            $responseData,
            JsonResponse::HTTP_OK
        );

    }
}