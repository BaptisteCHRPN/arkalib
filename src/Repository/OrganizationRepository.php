<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Organization;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

   public function findOrganizationsByUser(User $user): array
    {
        // This query fetch all active organizations related in connecteed user 
        return $this->createQueryBuilder('o')
            ->innerJoin('o.users', 'u') 
            ->where('u.id = :userId')
            ->andWhere('o.is_active = :isActive')
            ->setParameter('userId', $user->getId())
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return organization[] Returns an array of organization objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?organization
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
