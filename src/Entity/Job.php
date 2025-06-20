<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'jobs')]
class Job
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: JoblangLine::class, inversedBy: 'jobs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?JoblangLine $joblangLine = null;

    #[ORM\Column(type: 'string', length: 40)]
    private string $code;

    /**
     * @var Collection<int, Part>
     */
    #[ORM\OneToMany(targetEntity: Part::class, mappedBy: 'job', orphanRemoval: true)]
    private Collection $parts;

    public function __construct()
    {
        $this->parts = new ArrayCollection();
    }

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

    public function getJoblangLine(): ?JoblangLine
    {
        return $this->joblangLine;
    }

    public function setJoblangLine(?JoblangLine $line): self
    {
        $this->joblangLine = $line;
        $line->setJob($this);
        return $this;
    }

    public function getMetaData(): array
    {
        $parsed = $this->getJoblangLine()->getParsed();
        return $parsed["metaData"];
    }

    /**
     * @return Collection<int, Part>
     */
    public function getParts(): Collection
    {
        return $this->parts;
    }

    public function addPart(Part $part): static
    {
        if (!$this->parts->contains($part)) {
            $this->parts->add($part);
            $part->setJob($this);
        }

        return $this;
    }

    public function removePart(Part $part): static
    {
        if ($this->parts->removeElement($part)) {
            // set the owning side to null (unless already changed)
            if ($part->getJob() === $this) {
                $part->setJob(null);
            }
        }

        return $this;
    }
}