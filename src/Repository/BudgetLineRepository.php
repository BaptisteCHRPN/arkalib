<?php

namespace App\Repository;

use App\Entity\BudgetLine;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BudgetLine>
 */
class BudgetLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetLine::class);
    }

    public function findBudgetByOrganisation(Organization $organization): array
    {
        // This query fetch all active budget related in current organisation 
        return $this->createQueryBuilder('b')
            ->innerJoin('b.organizations', 'o') 
            ->where('o.id = :organizationId')
            ->andWhere('b.is_active = :isActive')
            ->setParameter('organizationId', $organization->getId())
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return BudgetLine[] Returns an array of BudgetLine objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?BudgetLine
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
