<?php

namespace App\Entity;

use App\Repository\BudgetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Trait\TraceableTrait;
use App\Entity\Trait\SoftDeleteTrait;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\UniqueConstraint(name: 'budget_slug_organization', columns: ['slug', 'organization_id'])]
class Budget
{
    use TraceableTrait;
    use SoftDeleteTrait;
    
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
    private bool $is_active = true;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\ManyToOne(inversedBy: 'budgets')]
    private ?Organization $organization = null;

    /**
     * @var Collection<int, BudgetLine>
     */
    #[ORM\OneToMany(targetEntity: BudgetLine::class, mappedBy: 'budget')]
    private Collection $budget_line;

    #[ORM\Column]
    private ?bool $is_closed = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\OneToMany(targetEntity: Category::class, mappedBy: 'budget', orphanRemoval: true)]
    private Collection $categories;

    public function __construct()
    {
        $this->budget_line = new ArrayCollection();
        $this->is_closed = 0;
        $this->categories = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function isClosed(): ?bool
    {
        return $this->is_closed;
    }

    public function setIsClosed(bool $is_closed): static
    {
        $this->is_closed = $is_closed;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->setBudget($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            // set the owning side to null (unless already changed)
            if ($category->getBudget() === $this) {
                $category->setBudget(null);
            }
        }

        return $this;
    }
}
