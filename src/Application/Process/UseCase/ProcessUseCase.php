<?php

namespace App\Application\Process\UseCase;

use App\Application\Process\PartPayload;
use App\Application\Process\ProcessRequestModel;
use App\Application\Process\ProcessResponseModel;
use App\Application\Process\Service\ActionPathTransformerInterface;
use App\Application\Process\Service\ProcessPersistenceServiceInterface;
use App\Application\Process\Service\ProductionPlanningServiceInterface;
use App\Service\PressSheetProviderInterface;

/**
 * Process Use Case - Orchestration only.
 *
 * Responsibility: Coordinate production planning, transformation, and persistence.
 * All business logic is delegated to specialized services.
 */
class ProcessUseCase
{
    public function __construct(
        private ProductionPlanningServiceInterface $planningService,
        private ProcessPersistenceServiceInterface $persistenceService,
        private ActionPathTransformerInterface $transformer,
        private PressSheetProviderInterface $pressSheetProvider,
    ) {}

    public function execute(ProcessRequestModel $request): ProcessResponseModel
    {
        $partsResponse = [];
        $partsWithPaths = [];

        foreach ($request->parts as $partPayload) {
            // Get press sheets based on paper weight
            $paperWeight = $this->extractPaperWeight($partPayload);
            $pressSheets = $this->pressSheetProvider->getPressSheets($paperWeight);

            // Plan production (ActionTree processing + ranking)
            $pathResults = $this->planningService->plan($partPayload, $pressSheets);
            $input = $this->planningService->getInput($partPayload, $pressSheets);

            // Transform to response format
            $actionPathsData = [];
            if ($input !== null) {
                foreach ($pathResults as $result) {
                    $actionPathsData[] = $this->transformer->toResponseArray($result, $partPayload, $input);
                }
            }

            $partsResponse[$partPayload->partId] = ['actionPaths' => $actionPathsData];
            $partsWithPaths[$partPayload->partId] = $actionPathsData;
        }

        // Persist request and results
        $processRequest = $this->persistenceService->persist($request, $partsWithPaths);

        return new ProcessResponseModel(
            $processRequest->getId(),
            $this->buildMetaData($processRequest->getId()),
            $partsResponse
        );
    }

    /**
     * Extract paper weight from part payload.
     */
    private function extractPaperWeight(PartPayload $partPayload): float
    {
        foreach ($partPayload->actions as $action) {
            if ($action->name->value === 'print') {
                return (float) ($action->params['paper']['weight'] ?? 120);
            }
        }
        return 120; // Default paper weight
    }

    /**
     * Build metadata for response.
     */
    private function buildMetaData(?int $jobId): array
    {
        return [
            'jobNumber' => 'PROCESS-001',
            'quantity' => 0,
            'jobId' => $jobId,
        ];
    }
}
