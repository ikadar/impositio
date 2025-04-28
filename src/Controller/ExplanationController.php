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

        $this->actionPath = $data;
    }

    protected function createResponse(): JsonResponse
    {
//        return new JsonResponse(
//            $this->actionPath,
//            JsonResponse::HTTP_OK
//        );

        $actionPath = array_reverse($this->actionPath);
        $responseData = [];

        $actionIds = array_keys($actionPath);
        $lastActionIndex = count($actionIds) - 1;

        $prevMachineAction = null;
        foreach ($actionPath as $machineId => $action) {

            $actionIndex = array_search($machineId, $actionIds);

            $nextAction = null;
            if (array_key_exists($actionIndex + 1, $actionIds)) {
                $nextAction = $actionPath[$actionIds[$actionIndex + 1]];
            }

            $actionData = [
                "actionType" => "print",
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
                ]
            ];

            if ($actionIndex === 0) {
                $actionData["inputSheet"] = [
                    "width" => $action["pressSheet"]["width"],
                    "height" => $action["pressSheet"]["height"],
                ];
            } else {
                $actionData["inputSheet"] = $prevMachineAction["cutSheet"];
            }

            $responseData[] = $actionData;

            if (
                ($nextAction !== null)
                &&
                (
                    ($nextAction["cutSheet"]["width"] !== $action["maxSheet"]["width"])
                    ||
                    ($nextAction["cutSheet"]["height"] !== $action["maxSheet"]["height"])
                )

            ) {
                $numberOfCuts = 0;
                $numberOfCuts += (($action["trimLines"]["top"]["y"] > 0) ? 2 : 0);
                $numberOfCuts += (($action["trimLines"]["left"]["x"] > 0) ? 2 : 0);


                $responseData[] = [
                    "actionType" => "trim",
                    "machine" => "cutter",
                    "trimLines" => $action["trimLines"],
                    "numberOfCuts" => $numberOfCuts,
                ];
            }

            $numberOfCuts = $action["cols"] - 1 + $action["rows"] - 1;

            if ($numberOfCuts) {
                $responseData[] = [
                    "actionType" => "cut",
                    "machine" => "cutter",
                    "numberOfCuts" => $numberOfCuts,
                ];
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

        return new JsonResponse(
            $responseData,
            JsonResponse::HTTP_OK
        );

    }
}