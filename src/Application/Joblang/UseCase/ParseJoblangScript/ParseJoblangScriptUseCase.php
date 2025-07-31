<?php

namespace App\Application\Joblang\UseCase\ParseJoblangScript;

use App\Domain\Joblang\Interfaces\JoblangServiceInterface;
use App\Entity\Job;
use App\Infrastructure\Mapper\JobMapper;
use Doctrine\ORM\EntityManagerInterface;

class ParseJoblangScriptUseCase
{
    public function __construct(
        private JoblangServiceInterface $joblangService,
        protected JobMapper $jobMapper,
        protected EntityManagerInterface $em
    ) {}

    public function execute(JoblangScriptParseRequestModel $request): JoblangScriptParseResponseModel
    {
        // Create and persist the JoblangScript + lines
        $joblangScript = $this->joblangService->parseScript($request->scriptContent);
        $joblangScriptEntity = $this->joblangService->persistScript($joblangScript);

        $repo = $this->em->getRepository(Job::class);
        $jobEntity = $repo->findWithParts($joblangScriptEntity->getLines()[0]->getJob()->getId());

        $domainJob = $this->jobMapper->toDomain($jobEntity); // Convert to domain Job object

        $parts = $domainJob->getParts();
        $metaData = $jobEntity->getMetaData();

        return new JoblangScriptParseResponseModel(
            $joblangScriptEntity->getId(),
            $domainJob,
            $parts,
            $metaData
        );
    }
}