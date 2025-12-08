<?php

namespace App\Entity;

use App\Repository\ProcessRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity to store requests from the /process endpoint.
 *
 * This is independent from the Joblang DSL flow (JoblangScript, JoblangLine, Job).
 */
#[ORM\Entity(repositoryClass: ProcessRequestRepository::class)]
#[ORM\Table(name: 'process_requests')]
class ProcessRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    /**
     * @var Collection<int, ProcessPart>
     */
    #[ORM\OneToMany(targetEntity: ProcessPart::class, mappedBy: 'processRequest', cascade: ['persist'], orphanRemoval: true)]
    private Collection $parts;

    public function __construct()
    {
        $this->parts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * @return Collection<int, ProcessPart>
     */
    public function getParts(): Collection
    {
        return $this->parts;
    }

    public function addPart(ProcessPart $part): static
    {
        if (!$this->parts->contains($part)) {
            $this->parts->add($part);
            $part->setProcessRequest($this);
        }

        return $this;
    }

    public function removePart(ProcessPart $part): static
    {
        if ($this->parts->removeElement($part)) {
            if ($part->getProcessRequest() === $this) {
                $part->setProcessRequest(null);
            }
        }

        return $this;
    }
}
