<?php

namespace App\Controller;

use App\Domain\Equipment\Machine;
use App\Domain\Geometry\AlignmentMode;
use App\Domain\Geometry\Dimensions;
use App\Domain\Layout\Calculator;
use App\Domain\Layout\CutSpacing;
use App\Domain\Layout\Interfaces\GridFittingInterface;
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
            ),
            $this->printFactory
        );

        $pressSheet = $this->printFactory->newRectangle(
            "pressSheet",
            0,
            0,
            $data["press-sheet"]["width"],
            $data["press-sheet"]["height"]
        );

        $zone = $this->printFactory->newInputSheet(
            "zone",
            0,
            0,
            $data["zone"]["width"],
            $data["zone"]["height"]
        );
        $zone->setGripMarginSize($data["zone"]["gripMargin"]["size"]);

        $gridFittings = $this->layoutCalculator->calculateGridFittings(
            $machine,
            $pressSheet,
            $zone,
        );

        $gf = [];
        /**
         * @var GridFittingInterface $gridFitting
         */
        foreach ($gridFittings as $gridFitting) {
            $gf[] = $gridFitting->toArray($machine, $pressSheet);
        }

        return new JsonResponse(
            $gf,
            JsonResponse::HTTP_OK
        );
    }

}