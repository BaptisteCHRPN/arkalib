<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, BudgetLine>
     */
    #[ORM\OneToMany(targetEntity: BudgetLine::class, mappedBy: 'category')]
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
            $budgetLine->setCategory($this);
        }

        return $this;
    }

    public function removeBudgetLine(BudgetLine $budgetLine): static
    {
        if ($this->budget_line->removeElement($budgetLine)) {
            // set the owning side to null (unless already changed)
            if ($budgetLine->getCategory() === $this) {
                $budgetLine->setCategory(null);
            }
        }

        return $this;
    }
}
