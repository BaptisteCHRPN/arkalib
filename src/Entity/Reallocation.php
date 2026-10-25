<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TraceableTrait;
use App\Enum\ReallocationInstance;
use App\Repository\ReallocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Décision d'une instance (CA, AG…) qui fait passer une partie du disponible
 * d'un budget vers un ou plusieurs autres budgets de la même organisation.
 *
 * Ni recette ni dépense : l'opération est neutre pour l'association.
 * Une réaffectation ne se modifie pas : pour la corriger, on l'annule
 * (soft delete, la trace reste) et on en saisit une nouvelle.
 */
#[ORM\Entity(repositoryClass: ReallocationRepository::class)]
class Reallocation
{
    use TraceableTrait;
    use SoftDeleteTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reallocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $decidedAt = null;

    #[ORM\Column(length: 20, enumType: ReallocationInstance::class)]
    private ?ReallocationInstance $instance = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pvReference = null;

    #[ORM\Column(length: 255)]
    private ?string $reason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Budget $sourceBudget = null;

    /**
     * @var Collection<int, ReallocationLine>
     */
    #[ORM\OneToMany(targetEntity: ReallocationLine::class, mappedBy: 'reallocation', cascade: ['persist', 'remove'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getDecidedAt(): ?\DateTime
    {
        return $this->decidedAt;
    }

    public function setDecidedAt(?\DateTime $decidedAt): static
    {
        $this->decidedAt = $decidedAt;

        return $this;
    }

    public function getInstance(): ?ReallocationInstance
    {
        return $this->instance;
    }

    public function setInstance(?ReallocationInstance $instance): static
    {
        $this->instance = $instance;

        return $this;
    }

    public function getPvReference(): ?string
    {
        return $this->pvReference;
    }

    public function setPvReference(?string $pvReference): static
    {
        $this->pvReference = $pvReference;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getSourceBudget(): ?Budget
    {
        return $this->sourceBudget;
    }

    public function setSourceBudget(?Budget $sourceBudget): static
    {
        $this->sourceBudget = $sourceBudget;

        return $this;
    }

    /**
     * @return Collection<int, ReallocationLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(ReallocationLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setReallocation($this);
        }

        return $this;
    }

    public function removeLine(ReallocationLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getReallocation() === $this) {
                $line->setReallocation(null);
            }
        }

        return $this;
    }

    /**
     * Annuler une réaffectation = la mettre en soft delete : elle sort de tous
     * les calculs mais reste consultable comme trace de la décision.
     */
    public function isCancelled(): bool
    {
        return null !== $this->getDeletedAt();
    }
}
