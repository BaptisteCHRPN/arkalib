<?php

namespace App\Entity;

use App\Repository\BudgetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
class Budget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $start_date = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $end_date = null;

    #[ORM\Column]
    private ?bool $is_active = null;

    #[ORM\ManyToOne(inversedBy: 'budgets')]
    private ?Organization $organization = null;

    /**
     * @var Collection<int, BudgetLine>
     */
    #[ORM\OneToMany(targetEntity: BudgetLine::class, mappedBy: 'budget')]
    private Collection $budget_line;

    public function __construct()
    {
        $this->budget_line = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->start_date;
    }

    public function setStartDate(\DateTime $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->end_date;
    }

    public function setEndDate(\DateTime $end_date): static
    {
        $this->end_date = $end_date;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
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

    /**
     * @return Collection<int, BudgetLine>
     */
    public function getBudgetLine(): Collection
    {
        return $this->budget_line;
    }

    public function addBudgetLine(BudgetLine $budgetLine): static
    {
        if (!$this->budget_line->contains($budgetLine)) {
            $this->budget_line->add($budgetLine);
            $budgetLine->setBudget($this);
        }

        return $this;
    }

    public function removeBudgetLine(BudgetLine $budgetLine): static
    {
        if ($this->budget_line->removeElement($budgetLine)) {
            // set the owning side to null (unless already changed)
            if ($budgetLine->getBudget() === $this) {
                $budgetLine->setBudget(null);
            }
        }

        return $this;
    }
}
