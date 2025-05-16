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

class MachinesController extends AbstractController
{
    public function __construct(
        protected EquipmentServiceInterface $equipmentService,
    )
    {
    }

    #[Route(path: '/machines', requirements: [], methods: ['POST'])]
    public function getTest(
    ): JsonResponse
    {
        return $this->createResponse($this->processPayload());
    }

    protected function processPayload(): array
    {
        $request = Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);

        $equipments = $this->equipmentService->load();

        $accessor = PropertyAccess::createPropertyAccessor();
        $machines = [];
        foreach ($data["actions"] as $action) {
            $machine = $accessor->getValue($equipments, "[" . $action["machine"] . "]");
            $machines[] = $machine;
        }

        $data["machines"] = $machines;
        $data["action-path"] = (object) $data["action-path"];

        return $data;
    }

    protected function createResponse($data): JsonResponse
    {
        return new JsonResponse(
            $data,
            JsonResponse::HTTP_OK
        );

    }
}