<?php

namespace App\Entity;

use App\Repository\JoblangScriptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JoblangScriptRepository::class)]
class JoblangScript
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $script = null;

    #[ORM\OneToMany(mappedBy: 'joblangScript', targetEntity: JoblangLine::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScript(): ?string
    {
        return $this->script;
    }

    public function setScript(string $script): static
    {
        $this->script = $script;

        return $this;
    }

    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(JoblangLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setJoblangScript($this);
        }

        return $this;
    }

    public function removeLine(JoblangLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            // Unset the owning side if needed
            if ($line->getJoblangScript() === $this) {
                $line->setJoblangScript(null);
            }
        }

        return $this;
    }
}
