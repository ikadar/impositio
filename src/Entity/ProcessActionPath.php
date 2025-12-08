<?php

namespace App\Entity;

use App\Repository\ProcessActionPathRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity storing computed action paths for ProcessPart.
 *
 * This is independent from the Joblang DSL flow.
 */
#[ORM\Entity(repositoryClass: ProcessActionPathRepository::class)]
#[ORM\Table(name: 'process_action_paths')]
class ProcessActionPath
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'actionPaths')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProcessPart $processPart = null;

    /**
     * Stores the computed action path data as JSON.
     */
    #[ORM\Column(type: 'json')]
    private array $json = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcessPart(): ?ProcessPart
    {
        return $this->processPart;
    }

    public function setProcessPart(?ProcessPart $processPart): static
    {
        $this->processPart = $processPart;
        return $this;
    }

    public function getJson(): array
    {
        return $this->json;
    }

    public function setJson(array $json): static
    {
        $this->json = $json;
        return $this;
    }
}
