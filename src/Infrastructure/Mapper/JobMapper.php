<?php

namespace App\Infrastructure\Mapper;

use App\Domain\Job\Interfaces\JobInterface as JobDomainInterface;
use App\Domain\Job\Job as JobDomain;
use App\Domain\Part\PartFactory;
use App\Entity\Job as JobEntity;

class JobMapper
{
    public function __construct(
        protected JobDomainInterface $jobDomain,
        protected PartFactory $partFactory,
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

        $partsData = $this->jobDomain->flatten($this->jobDomain->getParsed());

        $this->jobDomain->setParts([]);
        foreach ($partsData as $loop => $partData) {
            $part = $this->partFactory->create($partData);
            $part->setId(sprintf("PART%04d", $loop+1));
            $this->jobDomain->addPart($part);
        }

        return $this->jobDomain;
    }
}