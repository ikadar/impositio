<?php

namespace App\Infrastructure\Mapper;

use App\Domain\Job\Interfaces\JobInterface as JobDomainInterface;
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

        $partsData = [];
        foreach ($entity->getParts() as $part) {
            $json = $part->getJson();
            $json["id"] = $part->getId();
            $partsData[] = $json;
        }

        $this->jobDomain->setParts([]);

        foreach ($partsData as $partData) {
            $this->jobDomain->addPart(
                $this->partFactory->create($partData)
            );
        }

        return $this->jobDomain;
    }

}