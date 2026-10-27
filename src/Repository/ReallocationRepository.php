<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\Reallocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

        // Le filtre soft_delete cacherait les réaffectations annulées.
        $filters = $this->getEntityManager()->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');

        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            return $qb->getQuery()->getResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }
}
