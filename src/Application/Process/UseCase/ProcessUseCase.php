<?php

namespace App\Application\Process\UseCase;

use App\Application\Process\PartPayload;
use App\Application\Process\ProcessRequestModel;
use App\Application\Process\ProcessResponseModel;
use App\Domain\Action\Interfaces\ActionTreeInterface;
use App\Entity\ProcessActionPath;
use App\Entity\ProcessPart;
use App\Entity\ProcessRequest;
use App\Service\ActionParamsExtractorInterface;
use App\Service\PressSheetProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ProcessUseCase
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActionTreeInterface $actionTree,
        private ActionParamsExtractorInterface $paramsExtractor,
        private PressSheetProviderInterface $pressSheetProvider,
    ) {}

    public function execute(ProcessRequestModel $request): ProcessResponseModel
    {
        // Create ProcessRequest entity
        $processRequest = new ProcessRequest();
        $processRequest->setPayload($this->buildPayloadArray($request));

        // Collect response data for each part
        $partsResponse = [];

        // Create ProcessPart entities and process ActionTree
        foreach ($request->parts as $partPayload) {
            $processPart = $this->createProcessPart($partPayload);
            $processRequest->addPart($processPart);

            // Process ActionTree for this part
            $this->processActionTree($processPart, $partPayload);

            // Collect ActionPaths for response
            $actionPathsData = [];
            foreach ($processPart->getActionPaths() as $actionPath) {
                $pathJson = $actionPath->getJson();
                $pathJson['id'] = $actionPath->getId() ?? $pathJson['id'];
                $actionPathsData[] = $pathJson;
            }

            $partsResponse[$partPayload->partId] = [
                'actionPaths' => $actionPathsData,
            ];
        }

        // Persist
        $this->em->persist($processRequest);
        $this->em->flush();

        // Build hardcoded metaData (to be implemented later)
        $metaData = [
            'jobNumber' => 'PROCESS-001',
            'quantity' => 0,
            'jobId' => $processRequest->getId(),
        ];

        return new ProcessResponseModel(
            $processRequest->getId(),
            $metaData,
            $partsResponse
        );
    }

    /**
     * Process ActionTree for a part and create ActionPath entities.
     */
    private function processActionTree(ProcessPart $processPart, PartPayload $partPayload): void
    {
        // Get press sheets based on paper weight from params
        $paperWeight = $this->extractPaperWeight($partPayload);
        $pressSheets = $this->pressSheetProvider->getPressSheets($paperWeight);

        // Extract ActionTree input parameters
        $actionTreeInput = $this->paramsExtractor->extractForActionTree($partPayload, $pressSheets);

        if ($actionTreeInput === null) {
            // No print action or invalid params - skip ActionTree processing
            return;
        }

        try {
            // Process ActionTree
            $actionPaths = $this->actionTree->process(
                $actionTreeInput->abstractActions,
                $actionTreeInput->pressSheets,
                $actionTreeInput->zone,
                $actionTreeInput->openPoseDimensions,
                $actionTreeInput->closedPoseDimensions,
                $actionTreeInput->numberOfCopies,
                $actionTreeInput->numberOfColors,
                $actionTreeInput->paperWeight,
                $actionTreeInput->inking,
            );

            // Create ActionPath entities from results
            $this->createActionPathEntities($processPart, $actionPaths, $partPayload, $actionTreeInput);
        } catch (\Throwable $e) {
            // Log error but don't fail the request - ActionTree processing is optional
            // ActionPaths will just be empty for this part
            error_log("ActionTree processing failed: " . $e->getMessage());
        }
    }

    /**
     * Create ProcessActionPath entities from ActionTree results.
     */
    private function createActionPathEntities(
        ProcessPart $processPart,
        array $actionPaths,
        PartPayload $partPayload,
        \App\Application\Process\ActionTreeInput $input
    ): void {
        // Get best action paths (limit to top 10 by cost)
        $bestPaths = $this->selectBestActionPaths($actionPaths);

        foreach ($bestPaths as $actionPath) {
            $pathData = $this->buildPathData($actionPath, $partPayload, $input);

            $actionPathEntity = new ProcessActionPath();
            $actionPathEntity->setProcessPart($processPart);
            $actionPathEntity->setJson($pathData);

            $processPart->addActionPath($actionPathEntity);
        }
    }

    /**
     * Select best action paths based on cost (limit to top 10).
     */
    private function selectBestActionPaths(array $actionPaths): array
    {
        // Calculate costs and sort
        $pathsWithCost = [];
        foreach ($actionPaths as $actionPath) {
            $cost = $this->calculatePathCost($actionPath);
            $pathsWithCost[] = ['path' => $actionPath, 'cost' => $cost];
        }

        usort($pathsWithCost, fn($a, $b) => $a['cost'] <=> $b['cost']);

        // Return top 10 unique costs
        $uniqueCosts = [];
        $result = [];
        foreach ($pathsWithCost as $item) {
            if (!in_array($item['cost'], $uniqueCosts)) {
                $uniqueCosts[] = $item['cost'];
                $result[] = $item['path'];
            }
            if (count($result) >= 10) {
                break;
            }
        }

        return $result;
    }

    /**
     * Calculate total cost for an action path.
     */
    private function calculatePathCost(array $actionPath): float
    {
        $cost = 0;
        foreach ($actionPath as $node) {
            $nodeCost = $node->getTodo()['cost'] ?? 0;
            if (is_array($nodeCost)) {
                $nodeCost = $nodeCost['cost'] ?? 0;
            }
            $cost += $nodeCost;
        }
        return $cost;
    }

    /**
     * Build path data array for storage.
     */
    private function buildPathData(
        array $actionPath,
        PartPayload $partPayload,
        \App\Application\Process\ActionTreeInput $input
    ): array {
        $nodes = [];
        $designation = [];
        $cost = 0;
        $duration = 0;

        $pose = [
            'width' => $input->closedPoseDimensions->getWidth(),
            'height' => $input->closedPoseDimensions->getHeight(),
        ];

        foreach ($actionPath as $node) {
            $nodeArray = $node->toArray($node->getMachine(), $node->getPressSheet(), $pose);

            // Handle cost array format
            if (is_array($nodeArray['cost'])) {
                foreach ($nodeArray['cost'] as $costName => $additionalCost) {
                    if ($costName !== 'cost') {
                        $cost += $additionalCost;
                    }
                }
                $nodeArray['cost'] = $nodeArray['cost']['cost'] ?? 0;
            }

            $cost += $nodeArray['cost'];
            $duration += ($nodeArray['setupDuration'] ?? 0) + ($nodeArray['runDuration'] ?? 0);
            $designation[] = $nodeArray['machine'] ?? '';
            $nodes[] = $nodeArray;
        }

        $pressSheetText = '';
        if (!empty($nodes)) {
            $pressSheetText = sprintf(
                "%dx%d",
                $nodes[0]['pressSheet']['width'] ?? 0,
                $nodes[0]['pressSheet']['height'] ?? 0
            );
        }

        return [
            'id' => Uuid::v4()->toString(),
            'designation' => sprintf("(%s) %s Cost: %s€; Duration: %smin", $pressSheetText, implode(" > ", $designation), $cost, $duration),
            'nodes' => $nodes,
            'cost' => $cost,
            'duration' => $duration,
            'pressSheet' => sprintf("%smm", $pressSheetText),
            'openPoseDimensions' => sprintf(
                "%dx%d",
                $input->openPoseDimensions->getWidth(),
                $input->openPoseDimensions->getHeight()
            ),
            'closedPoseDimensions' => sprintf(
                "%dx%d",
                $input->closedPoseDimensions->getWidth(),
                $input->closedPoseDimensions->getHeight()
            ),
            'requiredParts' => $partPayload->requiredParts,
        ];
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

    private function createProcessPart(PartPayload $partPayload): ProcessPart
    {
        $processPart = new ProcessPart();
        $processPart->setPartId($partPayload->partId);
        $processPart->setActions(
            array_map(fn($action) => $action->toArray(), $partPayload->actions)
        );
        $processPart->setProperties($partPayload->properties);
        $processPart->setRequiredParts($partPayload->requiredParts);

        return $processPart;
    }

    private function buildPayloadArray(ProcessRequestModel $request): array
    {
        return [
            'parts' => array_map(fn($p) => $p->toArray(), $request->parts),
        ];
    }
}
