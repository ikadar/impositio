<?php

namespace App\Controller;

use App\Repository\ProcessActionPathRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class DisplayController extends AbstractController
{
    public function __construct(
        private ProcessActionPathRepository $actionPathRepository,
    ) {}

    #[Route(path: '/display/{jobId}/{partId}/{actionPathId}', methods: ['GET'])]
    public function display(string $jobId, string $partId, string $actionPathId): JsonResponse
    {
        $actionPath = $this->actionPathRepository->findByUuid($actionPathId);

        if ($actionPath === null) {
            throw new NotFoundHttpException("ActionPath not found: $actionPathId");
        }

        return new JsonResponse($actionPath->getJson());
    }
}