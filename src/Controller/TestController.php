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

        // todo: move
        foreach ($gridFittings as $l1 => $gridFitting) {
            $gridFittings[$l1]["firstTile"] = [
                "x" => $gridFitting["tiles"][0]["mmPositions"]->getLeft(),
                "y" => $gridFitting["tiles"][0]["mmPositions"]->getTop(),
                "width" => $gridFitting["tiles"][0]["mmPositions"]->getWidth(),
                "height" => $gridFitting["tiles"][0]["mmPositions"]->getHeight()
            ];

            $gridFittings[$l1]["firstTileWithCutBuffer"] = [
                "x" => $gridFitting["tiles"][0]["mmCutBufferPositions"]->getLeft(),
                "y" => $gridFitting["tiles"][0]["mmCutBufferPositions"]->getTop(),
                "width" => $gridFitting["tiles"][0]["mmCutBufferPositions"]->getWidth(),
                "height" => $gridFitting["tiles"][0]["mmCutBufferPositions"]->getHeight()
            ];

            $gridFittings[$l1]["cutSheet"] = [
                "gripMargin" => [
                    "size" => $gridFittings[$l1]["cutSheet"]->getGripMarginSize(),
                    "position" => strtolower($gridFittings[$l1]["cutSheet"]->getGripMarginPosition()->name),
                    "x" => $gridFittings[$l1]["cutSheet"]->getChildById("gripMargin")->getAbsoluteLeft(),
                    "y" => $gridFittings[$l1]["cutSheet"]->getChildById("gripMargin")->getAbsoluteTop(),
                    "width" => $gridFittings[$l1]["cutSheet"]->getChildById("gripMargin")->getWidth(),
                    "height" => $gridFittings[$l1]["cutSheet"]->getChildById("gripMargin")->getHeight(),
                ],
                "usableArea" => [
                    "x" => $gridFittings[$l1]["cutSheet"]->getChildById("usableArea")->getAbsoluteLeft(),
                    "y" => $gridFittings[$l1]["cutSheet"]->getChildById("usableArea")->getAbsoluteTop(),
                    "width" => $gridFittings[$l1]["cutSheet"]->getChildById("usableArea")->getWidth(),
                    "height" => $gridFittings[$l1]["cutSheet"]->getChildById("usableArea")->getHeight(),
                ],
                "x" => $gridFittings[$l1]["cutSheet"]->getAbsoluteLeft(),
                "y" => $gridFittings[$l1]["cutSheet"]->getAbsoluteTop(),
                "width" => $gridFittings[$l1]["cutSheet"]->getWidth(),
                "height" => $gridFittings[$l1]["cutSheet"]->getHeight(),
            ];

            $gridFittings[$l1]["pressSheet"] = json_decode($pressSheet->toJson(), true);
            $gridFittings[$l1]["maxSheet"] = json_decode($maxSheet->toJson(), true);
            $gridFittings[$l1]["minSheet"] = json_decode($minSheet->toJson(), true);

            $gridFittings[$l1]["layoutArea"] = [
                "x" => $gridFittings[$l1]["layoutArea"]->getAbsoluteLeft(),
                "y" => $gridFittings[$l1]["layoutArea"]->getAbsoluteTop(),
                "width" => $gridFittings[$l1]["layoutArea"]->getWidth(),
                "height" => $gridFittings[$l1]["layoutArea"]->getHeight(),
            ];


            foreach ($gridFitting["tiles"] as $l2 => $tile) {
                $gridFittings[$l1]["tiles"][$l2]["mmPositions"] = json_decode($tile["mmPositions"]->toJson(), true);
                $gridFittings[$l1]["tiles"][$l2]["mmCutBufferPositions"] = json_decode($tile["mmCutBufferPositions"]->toJson(), true);
            }
        }

        return new JsonResponse(
            $gridFittings,
            JsonResponse::HTTP_OK
        );
    }

}