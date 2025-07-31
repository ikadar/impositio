<?php

namespace App\Controller;

use App\Domain\Equipment\Interfaces\MachineInterface;
use App\Domain\Geometry\Interfaces\RectangleInterface;
use App\Domain\Sheet\Interfaces\InputSheetInterface;
use App\Entity\ActionPath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

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
        protected EntityManagerInterface $em,
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
        $data = $this->loadData($impositionId);

        return new JsonResponse(
            $data,
            JsonResponse::HTTP_OK
        );
    }

    public function loadData($impositionId): ?array
    {
        return $this->em->getRepository(ActionPath::class)->find($impositionId)->getJson();
    }

}