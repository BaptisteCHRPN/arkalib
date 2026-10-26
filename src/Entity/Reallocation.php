<?php

namespace App\Entity;

use App\Entity\Trait\SoftDeleteTrait;
use App\Entity\Trait\TraceableTrait;
use App\Enum\ReallocationInstance;
use App\Repository\ReallocationRepository;
use App\Validator\SufficientAvailableBalance;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Décision d'une instance (CA, AG…) qui fait passer une partie du disponible
 * d'un budget vers un ou plusieurs autres budgets de la même organisation.
 *
 * Ni recette ni dépense : l'opération est neutre pour l'association.
 * Une réaffectation ne se modifie pas : pour la corriger, on l'annule
 * (soft delete, la trace reste) et on en saisit une nouvelle.
 */
#[ORM\Entity(repositoryClass: ReallocationRepository::class)]
#[SufficientAvailableBalance]
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
    #[Assert\NotNull]
    private ?Organization $organization = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'Indiquez la date de la décision.')]
    private ?\DateTime $decidedAt = null;

    #[ORM\Column(length: 20, enumType: ReallocationInstance::class)]
    #[Assert\NotNull(message: 'Indiquez l\'instance qui a pris la décision.')]
    private ?ReallocationInstance $instance = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $pvReference = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Indiquez le motif de la réaffectation.')]
    #[Assert\Length(max: 255)]
    private ?string $reason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Choisissez le budget qui donne.')]
    private ?Budget $sourceBudget = null;

    /**
     * @var Collection<int, ReallocationLine>
     */
    #[ORM\OneToMany(targetEntity: ReallocationLine::class, mappedBy: 'reallocation', cascade: ['persist', 'remove'])]
    #[Assert\Count(min: 1, minMessage: 'Ajoutez au moins un budget qui reçoit.')]
    #[Assert\Valid]
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

    #[Assert\Callback]
    public function validateBudgets(ExecutionContextInterface $context): void
    {
        $source = $this->sourceBudget;

        if (null !== $source) {
            $this->validateBudget($context, $source, 'sourceBudget');
        }

        $seenTargets = [];

        // $index = clé de la collection, celle que le formulaire utilisera
        // pour afficher l'erreur sur la bonne ligne.
        foreach ($this->lines as $index => $line) {
            $target = $line->getTargetBudget();

            if (null === $target) {
                continue; // Déjà signalé par le NotNull de la ligne.
            }

            $path = sprintf('lines[%d].targetBudget', $index);

            if ($target === $source) {
                $context->buildViolation('Le budget qui donne ne peut pas être aussi un budget qui reçoit.')
                    ->atPath($path)
                    ->addViolation();
                continue;
            }

            if (in_array($target, $seenTargets, true)) {
                $context->buildViolation('Ce budget apparaît déjà sur une autre ligne : regroupez les montants sur une seule.')
                    ->atPath($path)
                    ->addViolation();
                continue;
            }

            $seenTargets[] = $target;
            $this->validateBudget($context, $target, $path);
        }
    }

    private function validateBudget(ExecutionContextInterface $context, Budget $budget, string $path): void
    {
        // Sécurité : un id de budget modifié dans la requête ne doit jamais
        // permettre d'atteindre une autre organisation. Message volontairement
        // neutre, pour ne rien révéler du budget visé.
        if ($budget->getOrganization() !== $this->organization) {
            $context->buildViolation('Ce budget n\'appartient pas à cette organisation.')
                ->atPath($path)
                ->addViolation();

            return;
        }

        if ($budget->isClosed()) {
            $context->buildViolation('Le budget « {{ name }} » est clôturé : il ne peut ni donner ni recevoir de réaffectation. Rouvrez-le d\'abord si cette décision doit s\'y appliquer.')
                ->setParameter('{{ name }}', (string) $budget->getName())
                ->atPath($path)
                ->addViolation();
        }
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
