<?php

namespace App\Infrastructure\Mapper;

use App\Domain\Job\Interfaces\JobInterface as JobDomainInterface;
use App\Domain\Job\Job as JobDomain;
use App\Entity\Job as JobEntity;

class JobMapper
{
    public function __construct(
        protected JobDomainInterface $jobDomain,
    )
    {
    }

    public function toEntity(JobDomainInterface $domain): JobEntity
    {
        return new JobEntity();
    }

    public function toDomain(JobEntity $entity): JobDomainInterface
    {
        $this->jobDomain->setParsed($entity->getJoblangLine()->getParsed());
        return $this->jobDomain;
    }
}