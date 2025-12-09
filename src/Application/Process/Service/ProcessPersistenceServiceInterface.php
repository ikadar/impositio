<?php

namespace App\Application\Process\Service;

use App\Application\Process\ProcessRequestModel;
use App\Entity\ProcessRequest;

/**
 * Interface for persistence of process requests and results.
 */
interface ProcessPersistenceServiceInterface
{
    /**
     * Persist a process request with its computed action paths.
     *
     * @param ProcessRequestModel $request The request model
     * @param array<string, array> $partsWithPaths Map of partId => action paths array
     * @return ProcessRequest The persisted ProcessRequest entity
     */
    public function persist(ProcessRequestModel $request, array $partsWithPaths): ProcessRequest;
}
