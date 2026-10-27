<?php

namespace App\Service;

use App\Entity\Budget;
use App\Entity\Reallocation;
use App\Entity\User;
use App\Repository\ReallocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Annulation et restauration d'une réaffectation, et ce qu'elle interdit sur
 * les budgets qu'elle cite. Comme pour SoftDeleteService, le flush reste à la
 * charge de l'appelant.
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
     * Pourquoi ce budget ne peut pas aller à la corbeille, ou null s'il le
     * peut : il est source ou cible d'une réaffectation active.
     */
    public function trashBlockedMessage(Budget $budget): ?string
    {
        $citing = $this->reallocationRepository()->findCiting($budget, includeCancelled: false);

        if ([] === $citing) {
            return null;
        }

        return sprintf(
            'Impossible de mettre ce budget à la corbeille : il est concerné par %s. Annulez d\'abord %s.',
            Reallocation::phrase($citing),
            1 === count($citing) ? 'cette réaffectation' : 'ces réaffectations',
        );
    }

    /**
     * Pourquoi ce budget ne peut pas être supprimé définitivement, ou null
     * s'il le peut : une réaffectation, même annulée, reste dans l'historique
     * et le cite toujours.
     */
    public function purgeBlockedMessage(Budget $budget): ?string
    {
        $citing = $this->reallocationRepository()->findCiting($budget, includeCancelled: true);

        if ([] === $citing) {
            return null;
        }

        return sprintf(
            'Impossible de supprimer définitivement ce budget : il est concerné par %s. Une réaffectation reste dans l\'historique de l\'association, même annulée : ce budget doit donc être conservé. Il reste dans la corbeille.',
            Reallocation::phrase($citing),
        );
    }

    private function reallocationRepository(): ReallocationRepository
    {
        return $this->entityManager->getRepository(Reallocation::class);
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
