<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'jobs')]
class Job
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JoblangScript::class, inversedBy: 'jobs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?JoblangScript $joblangScript = null;

    #[ORM\Column(type: 'string', length: 40)]
    private string $code;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getJoblangScript(): ?JoblangScript
    {
        return $this->joblangScript;
    }

    public function setJoblangScript(?JoblangScript $script): self
    {
        $this->joblangScript = $script;
        return $this;
    }
}