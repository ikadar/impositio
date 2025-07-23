<?php

namespace App\Infrastructure\Mapper;

use App\Domain\Job\Interfaces\JobInterface as JobDomainInterface;
use App\Domain\Job\Job as JobDomain;
use App\Domain\Part\PartFactory;
use App\Entity\Job as JobEntity;
use Symfony\Component\Uid\Uuid;

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

        $parts = $this->jobDomain->getParsed()["jobData"]["parts"];

        $this->addUuid($parts);

        $partsData = $this->jobDomain->flatten($parts);

        foreach ($partsData as $loop => $partData) {
            if (array_key_exists("requiredBy", $partData)) {
                $requiredBys = $partData["requiredBy"];
                $requirings = array_filter($partsData, function ($item) use ($requiredBys) {
                    return in_array($item["uuid"], $requiredBys);
                });

                foreach ($requirings as $requiring) {
                    foreach ($partsData as $loop2 => $partData2) {
                        if ($partData2["uuid"] == $requiring["uuid"]) {
                            $partsData[$loop2]["required_parts"][] = $partData["uuid"];
                        }
                    }
                }
            }
        }

        $this->jobDomain->setParts([]);

        foreach ($partsData as $loop => $partData) {
            $part = $this->partFactory->create($partData);
            $part->setId(sprintf("PART%04d", $loop+1));
            $this->jobDomain->addPart($part);
        }

        $parts = $this->jobDomain->getParts();
        foreach ($parts as $loop => $part) {
            $requiredPartIds = [];
            foreach ($part->getRequiredParts() as $requiredPartUuid) {
                $requiredPartIds[] = $this->getPartIdByUUid($parts, $requiredPartUuid);
            }
            $part->setRequiredParts($requiredPartIds);
        }
        $this->jobDomain->setParts($parts);

        return $this->jobDomain;
    }

    public function addUuid(&$parts)
    {
        foreach ($parts as $loop => $part) {
            if (is_array($part)) {
                if (array_key_exists("type", $part)) {
                    $parts[$loop]["uuid"] = Uuid::v4()->toString();
                } else {
                    $this->addUuid($parts[$loop]);
                }
            }
        }
    }

    public function getPartIdByUUid($parts, $uuid) {
        $part = array_values(array_filter($parts, function ($item) use ($uuid) {
            return $item->getUuid() == $uuid;
        }))[0];
        return $part->getId();
    }

}