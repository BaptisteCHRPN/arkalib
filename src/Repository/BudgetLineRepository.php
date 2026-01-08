<?php

namespace App\Repository;

use App\Entity\Budget;
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

    public function sumExpensesBudget(Budget $budget): float
    {
        $result = $this->createQueryBuilder('bl')
            ->select('SUM(bl.amount)')
            ->where('bl.budget = :budget')
            ->andWhere('bl.is_expense = :isExpense')
            ->setParameter('budget', $budget)
            ->setParameter('isExpense', true)
            ->getQuery()
            ->getSingleScalarResult(); // allow to have a simple value in return and not an array or an object

        return $result ?? 0; // if result is null, return 0 instead
    }

     public function sumIncomesBudget(Budget $budget): float
    {
        $result = $this->createQueryBuilder('bl')
            ->select('SUM(bl.amount)')
            ->where('bl.budget = :budget')
            ->andWhere('bl.is_expense = :isExpense')
            ->setParameter('budget', $budget)
            ->setParameter('isExpense', false)
            ->getQuery()
            ->getSingleScalarResult(); // allow to have a simple value in return and not an array or an object

        return $result ?? 0; // if result is null, return 0 instead
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
