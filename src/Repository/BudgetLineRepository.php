<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Repository\Trait\SearchesTextFieldsTrait;
use App\Service\BudgetBalanceCalculator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BudgetLine>
 */
class BudgetLineRepository extends ServiceEntityRepository
{
    use SearchesTextFieldsTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetLine::class);
    }

    /**
     * Recherche une ligne par nom, description, budget ou organisation.
     *
     * @return BudgetLine[]
     */
    public function search(?string $term): array
    {
        return $this->searchQueryBuilder($term, ['name', 'description', 'b.name', 'o.name'], 'bl')
            ->leftJoin('bl.budget', 'b')
            ->leftJoin('b.organization', 'o')
            ->addSelect('b', 'o')
            ->getQuery()
            ->getResult();
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
        return $this->sumByCategory($budget, true);
    }

    public function sumIncomesByCategory(Budget $budget): array
    {
        return $this->sumByCategory($budget, false);
    }

    /**
     * Prévu et réalisé par catégorie, en euros.
     *
     * Deux requêtes, car le prévu se compte par ligne et le réalisé par
     * transaction : joindre les deux dans un même SUM compterait chaque ligne
     * autant de fois qu'elle a de transactions, et inversement.
     *
     * Une transaction rattachée à plusieurs lignes d'une même catégorie y
     * compte une fois ; rattachée à des lignes de catégories différentes, elle
     * compte dans chacune.
     *
     * @return array<array{id: int, name: string, parent_id: ?int, parent_name: ?string, total: float, real_total: float}>
     */
    private function sumByCategory(Budget $budget, bool $isExpense): array
    {
        $rows = $this->createQueryBuilder('bl')
            ->select(
                'c.id',
                'c.name',
                'parent.id as parent_id',
                'parent.name as parent_name',
                'SUM(bl.amount) as total',
            )
            ->innerJoin('bl.category', 'c')
            ->leftJoin('c.parentCategory', 'parent')
            ->where('bl.budget = :budget')
            ->andWhere('bl.is_expense = :isExpense')
            ->andWhere('bl.is_active = :isActive')
            ->setParameter('budget', $budget)
            ->setParameter('isExpense', $isExpense)
            ->setParameter('isActive', true)
            ->groupBy('c.id, parent.id')
            ->addOrderBy('parent.name', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        // DISTINCT et non GROUP BY : MariaDB refuserait t.amount non agrégé
        // en mode ONLY_FULL_GROUP_BY.
        $transactions = $this->createQueryBuilder('bl')
            ->select('DISTINCT c.id as category_id', 't.id as transaction_id', 't.amount')
            ->innerJoin('bl.category', 'c')
            ->innerJoin('bl.transactions', 't')
            ->where('bl.budget = :budget')
            ->andWhere('bl.is_expense = :isExpense')
            ->andWhere('bl.is_active = :isActive')
            ->setParameter('budget', $budget)
            ->setParameter('isExpense', $isExpense)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getResult();

        $realizedInCents = [];
        foreach ($transactions as $transaction) {
            $categoryId = $transaction['category_id'];
            $realizedInCents[$categoryId] = ($realizedInCents[$categoryId] ?? 0)
                + BudgetBalanceCalculator::toCents($transaction['amount']);
        }

        return array_map(fn(array $row) => [
            ...$row,
            'total' => BudgetBalanceCalculator::toCents($row['total']) / 100,
            'real_total' => ($realizedInCents[$row['id']] ?? 0) / 100,
        ], $rows);
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
