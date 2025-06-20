<?php

namespace App\Entity;

use App\Repository\JoblangLineRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JoblangLineRepository::class)]
class JoblangLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: JoblangScript::class, inversedBy: 'jobs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?JoblangScript $joblangScript = null;

    #[ORM\OneToOne(mappedBy: 'joblangLine', targetEntity: Job::class, cascade: ['remove'], orphanRemoval: true)]
    private Job $job;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $source = null;

    #[ORM\Column]
    private array $parsed = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getParsed(): array
    {
        return $this->parsed;
    }

    public function setParsed(array $parsed): static
    {
        $this->parsed = $parsed;

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

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): self
    {
        $this->job = $job;
        return $this;
    }

}
