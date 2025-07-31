<?php

namespace App\Entity;

use App\Repository\PartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartRepository::class)]
class Part
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $partId = null;

    #[ORM\ManyToOne(inversedBy: 'parts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Job $job = null;

    /**
     * @var Collection<int, ActionPath>
     */
    #[ORM\OneToMany(targetEntity: ActionPath::class, mappedBy: 'part', orphanRemoval: true)]
    private Collection $actionPaths;

    #[ORM\Column]
    private array $json = [];

    public function __construct()
    {
        $this->actionPaths = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartId(): ?string
    {
        return $this->partId;
    }

    public function setPartId(string $partId): static
    {
        $this->partId = $partId;

        return $this;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    public function setJob(?Job $job): static
    {
        $this->job = $job;

        if ($job !== null && !$job->getParts()->contains($this)) {
            $job->addPart($this); // sync inverse side
        }

        return $this;
    }

    /**
     * @return Collection<int, ActionPath>
     */
    public function getActionPaths(): Collection
    {
        return $this->actionPaths;
    }

    public function addActionPath(ActionPath $actionPath): static
    {
        if (!$this->actionPaths->contains($actionPath)) {
            $this->actionPaths->add($actionPath);
            $actionPath->setPartId($this);
        }

        return $this;
    }

    public function removeActionPath(ActionPath $actionPath): static
    {
        if ($this->actionPaths->removeElement($actionPath)) {
            // set the owning side to null (unless already changed)
            if ($actionPath->getPartId() === $this) {
                $actionPath->setPartId(null);
            }
        }

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
