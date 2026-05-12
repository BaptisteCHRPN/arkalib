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
            ->andWhere('bl.is_active = :isActive')
            ->setParameter('budget', $budget)
            ->setParameter('isExpense', true)
            ->setParameter('isActive', true)
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
            ->andWhere('bl.is_active = :isActive')
            ->setParameter('budget', $budget)
            ->setParameter('isExpense', false)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getSingleScalarResult(); // allow to have a simple value in return and not an array or an object

        return $result ?? 0; // if result is null, return 0 instead
    }

    public function sumExpensesByCategory(Budget $budget): array
    {
        return $this->createQueryBuilder('bl')
            ->select(
                'c.id',
                'c.name',
                'parent.id as parent_id',
                'parent.name as parent_name',
                'SUM(bl.amount) as total',
                'SUM(t.amount) as real_total'  // Somme des transactions
            )
            ->innerJoin('bl.category', 'c')
            ->leftJoin('c.parentCategory', 'parent')
            ->leftJoin('bl.transactions', 't')  // Jointure avec les transactions
            ->where('bl.budget = :budget')
            ->andWhere('bl.is_expense = :isExpense')
            ->andWhere('bl.is_active = :isActive')
            ->setParameter('budget', $budget)
            ->setParameter('isExpense', true)
            ->setParameter('isActive', true)
            ->groupBy('c.id, parent.id')
            ->addOrderBy('parent.name', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumIncomesByCategory(Budget $budget): array
    {
        return $this->createQueryBuilder('bl')
            ->select(
                'c.id',
                'c.name',
                'parent.id as parent_id',
                'parent.name as parent_name',
                'SUM(bl.amount) as total',
                'SUM(t.amount) as real_total'  // Somme des transactions
            )
            ->innerJoin('bl.category', 'c')
            ->leftJoin('c.parentCategory', 'parent')
            ->leftJoin('bl.transactions', 't')  // Jointure avec les transactions
            ->where('bl.budget = :budget')
            ->andWhere('bl.is_expense = :isExpense')
            ->andWhere('bl.is_active = :isActive')
            ->setParameter('budget', $budget)
            ->setParameter('isExpense', false)
            ->setParameter('isActive', true)
            ->groupBy('c.id, parent.id')
            ->addOrderBy('parent.name', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDeletedByOrganization(Organization $organization, bool $isExpense): array
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        $results = $this->createQueryBuilder('bl')
            ->join('bl.budget', 'b')
            ->where('b.organization = :organization')
            ->andWhere('bl.deleted_at IS NOT NULL')
            ->andWhere('bl.is_expense = :isExpense')
            ->setParameter('organization', $organization)
            ->setParameter('isExpense', $isExpense)
            ->orderBy('bl.deleted_at', 'DESC')
            ->getQuery()
            ->getResult();

        $this->getEntityManager()->getFilters()->enable('soft_delete');

        return $results;
    }
}
