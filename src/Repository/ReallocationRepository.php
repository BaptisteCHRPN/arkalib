<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\Reallocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reallocation>
 */
class ReallocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reallocation::class);
    }

    /**
     * Réaffectations non annulées données par ce budget, la plus récente
     * d'abord, lignes comprises.
     *
     * La condition sur deleted_at double volontairement le filtre soft_delete,
     * comme dans ReallocationLineRepository.
     *
     * @return Reallocation[]
     */
    public function findActiveGivenBy(Budget $budget): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('l')
            ->join('r.lines', 'l')
            ->where('r.sourceBudget = :budget')
            ->andWhere('r.deleted_at IS NULL')
            ->setParameter('budget', $budget)
            ->orderBy('r.decidedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réaffectations où ce budget est source ou cible, la plus récente d'abord.
     *
     * @param bool $includeCancelled les annulées aussi : elles restent dans
     *                               l'historique et citent toujours le budget
     *
     * @return Reallocation[]
     */
    public function findCiting(Budget $budget, bool $includeCancelled): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.lines', 'l')
            ->where('r.sourceBudget = :budget OR l.targetBudget = :budget')
            ->setParameter('budget', $budget)
            ->orderBy('r.decidedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC');

        if (!$includeCancelled) {
            return $qb->andWhere('r.deleted_at IS NULL')->getQuery()->getResult();
        }

        return $this->withoutSoftDeleteFilter(fn() => $qb->getQuery()->getResult());
    }

    /**
     * Toutes les réaffectations de l'organisation, annulées comprises, la plus
     * récente d'abord, avec leurs budgets et leurs auteurs.
     *
     * @return Reallocation[]
     */
    public function findForOrganization(Organization $organization): array
    {
        return $this->withoutSoftDeleteFilter(fn() => $this->withEverything()
            ->where('r.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('r.decidedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult());
    }

    /**
     * Une réaffectation, même annulée, si elle appartient à cette organisation.
     */
    public function findOneForOrganization(int $id, Organization $organization): ?Reallocation
    {
        return $this->withoutSoftDeleteFilter(fn() => $this->withEverything()
            ->where('r.id = :id')
            ->andWhere('r.organization = :organization')
            ->setParameter('id', $id)
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getOneOrNullResult());
    }

    /**
     * Budgets et auteurs chargés dans la même requête. Indispensable pour une
     * réaffectation annulée : un budget passé depuis à la corbeille, chargé
     * plus tard filtre actif, lèverait une EntityNotFoundException.
     */
    private function withEverything(): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->addSelect('l', 's', 't', 'cb', 'db')
            ->join('r.sourceBudget', 's')
            ->join('r.lines', 'l')
            ->join('l.targetBudget', 't')
            ->leftJoin('r.created_by', 'cb')
            ->leftJoin('r.deleted_by', 'db');
    }

    /**
     * Le filtre soft_delete cacherait les réaffectations annulées et les
     * budgets à la corbeille qu'elles citent.
     *
     * @template T
     *
     * @param callable(): T $query
     *
     * @return T
     */
    private function withoutSoftDeleteFilter(callable $query): mixed
    {
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');

        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            return $query();
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }
}
