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
}
