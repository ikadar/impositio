<?php

namespace App\Controller;

use App\Application\Process\PartPayload;
use App\Application\Process\ProcessRequestModel;
use App\Application\Process\UseCase\ProcessUseCase;
use App\Service\ActionValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProcessController extends AbstractController
{
    public function __construct(
        private ActionValidatorInterface $actionValidator,
        private ProcessUseCase $processUseCase,
    ) {}

    #[Route(path: '/process', methods: ['POST'])]
    public function process(Request $request): JsonResponse
    {
        // Parse JSON payload
        $payload = json_decode($request->getContent(), true);
        if ($payload === null) {
            return new JsonResponse(
                ['error' => 'Invalid JSON payload', 'code' => 'INVALID_JSON'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // Validate payload structure
        if (!isset($payload['parts']) || !is_array($payload['parts'])) {
            return new JsonResponse(
                ['error' => "Missing or invalid 'parts' field", 'code' => 'MISSING_PARTS'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // Process and validate each part
        $partPayloads = [];
        foreach ($payload['parts'] as $index => $partData) {
            // Validate partId
            if (!isset($partData['partId']) || !is_string($partData['partId'])) {
                return new JsonResponse(
                    ['error' => "Part at index {$index} is missing required 'partId' field", 'code' => 'MISSING_PART_ID'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            // Validate actions array exists
            if (!isset($partData['actions']) || !is_array($partData['actions'])) {
                return new JsonResponse(
                    ['error' => "Part '{$partData['partId']}' is missing required 'actions' field", 'code' => 'MISSING_ACTIONS'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            // Validate actions using ActionValidator
            $validationResult = $this->actionValidator->validate($partData['actions']);
            if (!$validationResult->isValid) {
                return new JsonResponse(
                    $validationResult->getErrorResponse(),
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            $partPayloads[] = PartPayload::fromArray($partData, $validationResult->actions);
        }

        // Execute use case
        $requestModel = new ProcessRequestModel($partPayloads);
        $responseModel = $this->processUseCase->execute($requestModel);

        // Return response in TestController::getTest() compatible format
        return new JsonResponse(
            [
                [
                    'metaData' => $responseModel->metaData,
                    'parts' => $responseModel->parts,
                ]
            ],
            JsonResponse::HTTP_OK
        );
    }
}
