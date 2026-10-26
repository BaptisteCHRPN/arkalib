<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Trait\TraceableTrait;
use App\Entity\Trait\SoftDeleteTrait;
use App\Enum\TransactionDirection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;



#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: '`transaction`')]
class Transaction
{
    use TraceableTrait;
    use SoftDeleteTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column(length: 255)]
    private ?string $payment_method = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?bool $is_active = null;

    /**
     * @var Collection<int, BudgetLine>
     */
    #[ORM\ManyToMany(targetEntity: BudgetLine::class, inversedBy: 'transactions')]
    private Collection $budget_line;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachment = null;

    public function __construct()
    {
        $this->budget_line = new ArrayCollection();
        $this->is_active = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->payment_method;
    }

    public function setPaymentMethod(string $payment_method): static
    {
        $this->payment_method = $payment_method;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

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
        }

        return $this;
    }

    public function removeBudgetLine(BudgetLine $budgetLine): static
    {
        $this->budget_line->removeElement($budgetLine);

        return $this;
    }

    /**
     * Encaissement si toutes les lignes rattachées sont des recettes,
     * décaissement si ce sont toutes des dépenses.
     *
     * Null s'il n'y a aucune ligne, ou si les lignes mélangent recettes et
     * dépenses : ce mélange est interdit à la saisie, mais des données
     * antérieures à cette règle peuvent encore le contenir.
     */
    /**
     * Les lignes à la corbeille ne comptent pas, comme en base où le filtre
     * soft_delete les écarte : une ligne tout juste supprimée en mémoire,
     * avant le flush, doit déjà être ignorée.
     */
    public function getDirection(): ?TransactionDirection
    {
        $lines = $this->activeBudgetLines();
        $hasIncome = $lines->exists(fn(int $i, BudgetLine $line) => !$line->isExpense());
        $hasExpense = $lines->exists(fn(int $i, BudgetLine $line) => $line->isExpense());

        return match (true) {
            $hasIncome && !$hasExpense => TransactionDirection::CASH_IN,
            $hasExpense && !$hasIncome => TransactionDirection::CASH_OUT,
            default => null,
        };
    }

    /**
     * @return Collection<int, BudgetLine>
     */
    private function activeBudgetLines(): Collection
    {
        return $this->budget_line->filter(fn(BudgetLine $line) => null === $line->getDeletedAt());
    }

    #[Assert\Callback]
    public function validateSingleDirection(ExecutionContextInterface $context): void
    {
        // L'absence de ligne est déjà refusée par le formulaire (Count min 1) :
        // on ne signale ici que le mélange recettes / dépenses.
        if (!$this->activeBudgetLines()->isEmpty() && null === $this->getDirection()) {
            $context->buildViolation('Une transaction est soit un encaissement, soit un décaissement : sélectionne uniquement des recettes, ou uniquement des dépenses.')
                ->atPath('budget_line')
                ->addViolation();
        }
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getAttachment(): ?string
    {
        return $this->attachment;
    }

    public function setAttachment(?string $attachment): static
    {
        $this->attachment = $attachment;

        return $this;
    }
}
