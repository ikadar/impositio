<?php

namespace App\Application\Process\UseCase;

use App\Application\Process\PartPayload;
use App\Application\Process\ProcessRequestModel;
use App\Application\Process\ProcessResponseModel;
use App\Entity\ProcessPart;
use App\Entity\ProcessRequest;
use Doctrine\ORM\EntityManagerInterface;

class ProcessUseCase
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function execute(ProcessRequestModel $request): ProcessResponseModel
    {
        // Create ProcessRequest entity
        $processRequest = new ProcessRequest();
        $processRequest->setPayload($this->buildPayloadArray($request));

        // Create ProcessPart entities
        foreach ($request->parts as $partPayload) {
            $processPart = $this->createProcessPart($partPayload);
            $processRequest->addPart($processPart);
        }

        // Persist
        $this->em->persist($processRequest);
        $this->em->flush();

        // TODO: Phase 3 - ActionTree integration
        // For now, we only persist the request and parts.
        // ActionPath generation will be added in Phase 3.

        return new ProcessResponseModel($processRequest->getId());
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
