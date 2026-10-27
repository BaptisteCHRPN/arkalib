<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\RevenueProtectionOverride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RevenueProtectionOverride>
 */
class RevenueProtectionOverrideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RevenueProtectionOverride::class);
    }

    /**
     * Les forçages de ce budget, le plus récent d'abord, avec leur auteur.
     *
     * @return RevenueProtectionOverride[]
     */
    public function findByBudget(Budget $budget): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('u')
            ->join('o.forcedBy', 'u')
            ->andWhere('o.budget = :budget')
            ->setParameter('budget', $budget)
            ->orderBy('o.forcedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
