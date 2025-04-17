<?php

namespace App\Controller;

use App\Domain\Equipment\Machine;
use App\Domain\Geometry\AlignmentMode;
use App\Domain\Geometry\Dimensions;
use App\Domain\Layout\Calculator;
use App\Domain\Layout\CutSpacing;
use App\Domain\Sheet\PrintFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
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
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $machine = new Machine(
            $data["machine"]["id"],
            $data["machine"]["gripMargin"],
            new Dimensions(
                $data["machine"]["input-dimensions"]["min"]["width"],
                $data["machine"]["input-dimensions"]["min"]["height"]
            ),
            new Dimensions(
                $data["machine"]["input-dimensions"]["max"]["width"],
                $data["machine"]["input-dimensions"]["max"]["height"]
            )
        );

        $pressSheet = $this->printFactory->newRectangle(
            "pressSheet",
            0,
            0,
            $data["press-sheet"]["width"],
            $data["press-sheet"]["height"]
        );

        $minSheet = $this->printFactory->newRectangle(
            "maxSheet",
            0,
            0,
            $machine->getMinSheetDimensions()->getWidth(),
            $machine->getMinSheetDimensions()->getHeight()
        );
        $minSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        $maxSheet = $this->printFactory->newRectangle(
            "maxSheet",
            0,
            0,
            $machine->getMaxSheetDimensions()->getWidth(),
            $machine->getMaxSheetDimensions()->getHeight()
        );
        $maxSheet->alignTo($pressSheet, AlignmentMode::MiddleCenterToMiddleCenter);

        $zone = $this->printFactory->newInputSheet(
            "zone",
            0,
            0,
            $data["zone"]["width"],
            $data["zone"]["height"]
        );
        $zone->setGripMarginSize($data["zone"]["gripMargin"]["size"]);

        $boundingArea = $this->printFactory->newRectangle(
            "boundingArea",
            0,
            0,
            $machine->getMaxSheetDimensions()->getWidth(),
            $machine->getMaxSheetDimensions()->getHeight()
        );

        $cutSpacing = new CutSpacing(
            $data["cutSpacing"]["horizontal"],
            $data["cutSpacing"]["vertical"],
        );

        $gridFittings = $this->layoutCalculator->calculateGridFittings($machine, $boundingArea, $zone, $cutSpacing, $pressSheet, $maxSheet, $minSheet);

        return new JsonResponse(
            $gridFittings,
            JsonResponse::HTTP_OK
        );
    }

}