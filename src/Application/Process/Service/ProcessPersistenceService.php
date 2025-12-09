<?php

namespace App\Application\Process\Service;

use App\Application\Process\PartPayload;
use App\Application\Process\ProcessRequestModel;
use App\Entity\ProcessActionPath;
use App\Entity\ProcessPart;
use App\Entity\ProcessRequest;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for persisting process requests and results.
 *
 * Responsibility: Create and persist ProcessRequest, ProcessPart, and ProcessActionPath entities.
 */
class ProcessPersistenceService implements ProcessPersistenceServiceInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * Persist a process request with its computed action paths.
     *
     * @param ProcessRequestModel $request The request model
     * @param array<string, array> $partsWithPaths Map of partId => action paths array
     * @return ProcessRequest The persisted ProcessRequest entity
     */
    public function persist(ProcessRequestModel $request, array $partsWithPaths): ProcessRequest
    {
        $processRequest = new ProcessRequest();
        $processRequest->setPayload($this->buildPayloadArray($request));

        foreach ($request->parts as $partPayload) {
            $processPart = $this->createProcessPart($partPayload);
            $processRequest->addPart($processPart);

            // Add action paths for this part
            $actionPaths = $partsWithPaths[$partPayload->partId] ?? [];
            foreach ($actionPaths as $pathData) {
                $actionPathEntity = new ProcessActionPath();
                $actionPathEntity->setJson($pathData);
                $processPart->addActionPath($actionPathEntity);
            }
        }

        $this->em->persist($processRequest);
        $this->em->flush();

        return $processRequest;
    }

    /**
     * Create a ProcessPart entity from PartPayload.
     */
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

    /**
     * Build payload array from request model.
     */
    private function buildPayloadArray(ProcessRequestModel $request): array
    {
        return [
            'parts' => array_map(fn($p) => $p->toArray(), $request->parts),
        ];
    }
}
