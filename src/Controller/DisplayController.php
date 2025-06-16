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

class DisplayController extends AbstractController
{
    protected MachineInterface $machine;
    protected RectangleInterface $pressSheet;
    protected InputSheetInterface $zone;
    protected array $actionPath;
    protected array $pose;
    protected array $abstractActionData;

    public function __construct(
        protected KernelInterface $kernel,
    )
    {
    }

    #[Route(path: '/display/{jobId}/{partId}/{impositionId}', requirements: [], methods: ['GET'])]
    public function display(
        $jobId,
        $partId,
        $impositionId,
    ): JsonResponse
    {
        $data = $this->loadData($jobId, $partId, $impositionId);

        return new JsonResponse(
            $data,
            JsonResponse::HTTP_OK
        );


    }

    public function loadData($jobId, $partId, $impositionId): ?array
    {
        $path = sprintf("%s/data/%s/parts/%s.json", $this->kernel->getProjectDir(), $jobId, $partId);

        $jsonData = file_get_contents($path);
        $data = json_decode($jsonData, true);

        foreach ($data as $item) {
            if ($item['id'] === $impositionId) {
                return $item;
            }
        }
        return null;
    }

}