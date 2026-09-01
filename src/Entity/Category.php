<?php
namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Trait\TraceableTrait;
use App\Entity\Trait\SoftDeleteTrait;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[ORM\Index(name: "idx_budget_id", columns: ["budget_id"])]
#[ORM\Index(name: "idx_parent_category_id", columns: ["parent_category_id"])]
#[ORM\Index(name: "idx_budget_parent", columns: ["budget_id", "parent_category_id"])]
class Category
{
    use TraceableTrait;
    use SoftDeleteTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Budget::class, inversedBy: 'categories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Budget $budget = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'subCategories')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Category $parentCategory = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentCategory', cascade: ['remove'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $subCategories;

    /**
     * @var Collection<int, BudgetLine>
     */
    #[ORM\OneToMany(targetEntity: BudgetLine::class, mappedBy: 'category')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $budgetLines;

    public function __construct()
    {
        $this->budgetLines = new ArrayCollection();
        $this->subCategories = new ArrayCollection();
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

    public function getBudget(): ?Budget
    {
        return $this->budget;
    }

    public function setBudget(?Budget $budget): static
    {
        $this->budget = $budget;
        return $this;
    }

    public function getParentCategory(): ?self
    {
        return $this->parentCategory;
    }

    public function setParentCategory(?self $parentCategory): static
    {
        $this->parentCategory = $parentCategory;
        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getSubCategories(): Collection
    {
        return $this->subCategories;
    }

    public function addSubCategory(Category $subCategory): static
    {
        if (!$this->subCategories->contains($subCategory)) {
            $this->subCategories->add($subCategory);
            $subCategory->setParentCategory($this);
        }
        return $this;
    }

    public function removeSubCategory(Category $subCategory): static
    {
        if ($this->subCategories->removeElement($subCategory)) {
            if ($subCategory->getParentCategory() === $this) {
                $subCategory->setParentCategory(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, BudgetLine>
     */
    public function getBudgetLines(): Collection
    {
        return $this->budgetLines;
    }

    public function addBudgetLine(BudgetLine $budgetLine): static
    {
        if (!$this->budgetLines->contains($budgetLine)) {
            $this->budgetLines->add($budgetLine);
            $budgetLine->setCategory($this);
        }
        return $this;
    }

    public function removeBudgetLine(BudgetLine $budgetLine): static
    {
        if ($this->budgetLines->removeElement($budgetLine)) {
            if ($budgetLine->getCategory() === $this) {
                $budgetLine->setCategory(null);
            }
        }
        return $this;
    }
}