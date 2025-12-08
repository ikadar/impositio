<?php

namespace App\Entity;

use App\Repository\ProcessPartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity representing a part from the /process endpoint.
 *
 * This is independent from the Joblang DSL flow.
 */
#[ORM\Entity(repositoryClass: ProcessPartRepository::class)]
#[ORM\Table(name: 'process_parts')]
class ProcessPart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $partId;

    #[ORM\ManyToOne(inversedBy: 'parts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProcessRequest $processRequest = null;

    /**
     * Stores the actions array from the payload.
     */
    #[ORM\Column(type: 'json')]
    private array $actions = [];

    /**
     * Stores the properties from the payload.
     */
    #[ORM\Column(type: 'json')]
    private array $properties = [];

    /**
     * Stores the required_parts array from the payload.
     */
    #[ORM\Column(type: 'json')]
    private array $requiredParts = [];

    /**
     * @var Collection<int, ProcessActionPath>
     */
    #[ORM\OneToMany(targetEntity: ProcessActionPath::class, mappedBy: 'processPart', cascade: ['persist'], orphanRemoval: true)]
    private Collection $actionPaths;

    public function __construct()
    {
        $this->actionPaths = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartId(): string
    {
        return $this->partId;
    }

    public function setPartId(string $partId): static
    {
        $this->partId = $partId;
        return $this;
    }

    public function getProcessRequest(): ?ProcessRequest
    {
        return $this->processRequest;
    }

    public function setProcessRequest(?ProcessRequest $processRequest): static
    {
        $this->processRequest = $processRequest;
        return $this;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function setActions(array $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setProperties(array $properties): static
    {
        $this->properties = $properties;
        return $this;
    }

    public function getRequiredParts(): array
    {
        return $this->requiredParts;
    }

    public function setRequiredParts(array $requiredParts): static
    {
        $this->requiredParts = $requiredParts;
        return $this;
    }

    /**
     * @return Collection<int, ProcessActionPath>
     */
    public function getActionPaths(): Collection
    {
        return $this->actionPaths;
    }

    public function addActionPath(ProcessActionPath $actionPath): static
    {
        if (!$this->actionPaths->contains($actionPath)) {
            $this->actionPaths->add($actionPath);
            $actionPath->setProcessPart($this);
        }

        return $this;
    }

    public function removeActionPath(ProcessActionPath $actionPath): static
    {
        if ($this->actionPaths->removeElement($actionPath)) {
            if ($actionPath->getProcessPart() === $this) {
                $actionPath->setProcessPart(null);
            }
        }

        return $this;
    }
}
