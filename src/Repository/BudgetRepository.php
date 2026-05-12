<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Budget>
 */
class BudgetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Budget::class);
    }

    //    /**
    //     * @return Budget[] Returns an array of Budget objects
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

    //    public function findOneBySomeField($value): ?Budget
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findDeletedByOrganization(Organization $organization): array
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        $results = $this->createQueryBuilder('b')
            ->where('b.organization = :organization')
            ->andWhere('b.deleted_at IS NOT NULL')
            ->setParameter('organization', $organization)
            ->orderBy('b.deleted_at', 'DESC')
            ->getQuery()
            ->getResult();

        $this->getEntityManager()->getFilters()->enable('soft_delete');

        return $results;
    }
}
