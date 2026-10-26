<?php

namespace App\Service;

use App\Entity\Reallocation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Annulation et restauration d'une réaffectation. Comme pour SoftDeleteService,
 * le flush reste à la charge de l'appelant.
 */
final class ReallocationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private SoftDeleteService $softDeleteService,
    ) {
    }

    /**
     * Jamais bloquée : une cible qui a déjà dépensé l'argent passe en négatif,
     * et ce négatif est affiché.
     */
    public function cancel(Reallocation $reallocation, User $user): void
    {
        if ($reallocation->isCancelled()) {
            throw new \LogicException('Cette réaffectation est déjà annulée.');
        }

        $this->softDeleteService->softDelete($reallocation, $user);
    }

    /**
     * Mêmes règles qu'à la création. À appeler avant le flush : tant que la
     * réaffectation est annulée en base, elle n'est pas déjà déduite du
     * disponible de sa source.
     *
     * @return ConstraintViolationListInterface vide si la réaffectation a été restaurée
     */
    public function restore(Reallocation $reallocation): ConstraintViolationListInterface
    {
        if (!$reallocation->isCancelled()) {
            throw new \LogicException('Cette réaffectation n\'est pas annulée.');
        }

        $this->loadBudgets($reallocation);

        $violations = $this->validator->validate($reallocation);

        if (0 === count($violations)) {
            $this->softDeleteService->restore($reallocation);
        }

        return $violations;
    }

    /**
     * Un budget passé à la corbeille depuis l'annulation est caché par le
     * filtre soft_delete : le lire lèverait une EntityNotFoundException. On le
     * charge filtre désactivé pour que la validation puisse le refuser avec un
     * message. Le filtre est réactivé avant la validation, car le calcul du
     * disponible compte sur lui pour ignorer les transactions à la corbeille.
     */
    private function loadBudgets(Reallocation $reallocation): void
    {
        $filters = $this->entityManager->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');

        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            $budgets = [$reallocation->getSourceBudget()];

            foreach ($reallocation->getLines() as $line) {
                $budgets[] = $line->getTargetBudget();
            }

            foreach (array_filter($budgets) as $budget) {
                $this->entityManager->initializeObject($budget);
            }
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }
}
