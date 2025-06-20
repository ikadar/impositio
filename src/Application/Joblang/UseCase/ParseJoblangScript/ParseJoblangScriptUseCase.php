<?php

namespace App\Application\Joblang\UseCase\ParseJoblangScript;

use App\Domain\Joblang\Interfaces\JoblangServiceInterface;
use App\Infrastructure\Mapper\JobMapper;

class ParseJoblangScriptUseCase
{
    public function __construct(
        private JoblangServiceInterface $joblangService,
        protected JobMapper $jobMapper,
    ) {}

    public function execute(JoblangScriptParseRequestModel $request): JoblangScriptParseResponseModel
    {
        // Create and persist the JoblangScript + lines
        $joblangScript = $this->joblangService->parseAndPersistScript($request->scriptContent);

        // Map entity to domain
        $jobEntity = $joblangScript->getLines()[0]->getJob(); // Doctrine entity
        $domainJob = $this->jobMapper->toDomain($jobEntity); // Convert to domain Job object
        $parts = $domainJob->getParts();
        $metaData = $jobEntity->getMetaData();

        return new JoblangScriptParseResponseModel(
            $joblangScript->getId(),
            $domainJob,
            $parts,
            $metaData
        );
    }
}