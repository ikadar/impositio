<?php

namespace App\Infrastructure\Mapper;

use App\Domain\Part\Interfaces\PartInterface as PartDomainInterface;
use App\Domain\Part\Part as PartDomain;
use App\Entity\Part as PartEntity;

class PartMapper
{
    protected PartDomainInterface $partDomain;
    public function __construct(
    )
    {
    }

    public function toEntity(PartDomainInterface $domain): PartEntity
    {
        $entity = new PartEntity();
        $entity->setPartId($domain->getId());
        return $entity;
    }

    public function toDomain(PartEntity $entity): PartDomainInterface
    {
        return $this->partDomain;
    }
}