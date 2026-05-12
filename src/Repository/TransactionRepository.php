<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findByBudget(Budget $budget): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.budget_line', 'bl')
            ->addSelect('bl')
            ->where('bl.budget = :budget')
            ->setParameter('budget', $budget)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findDeletedByOrganization(Organization $organization): array
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        $results = $this->createQueryBuilder('t')
            ->join('t.budget_line', 'bl')
            ->join('bl.budget', 'b')
            ->where('b.organization = :organization')
            ->andWhere('t.deleted_at IS NOT NULL')
            ->setParameter('organization', $organization)
            ->orderBy('t.deleted_at', 'DESC')
            ->getQuery()
            ->getResult();

        $this->getEntityManager()->getFilters()->enable('soft_delete');

        return $results;
    }

    public function findDeletedByBudget(Budget $budget): array
    {
        $this->getEntityManager()->getFilters()->disable('soft_delete');

        $results = $this->createQueryBuilder('t')
            ->join('t.budget_line', 'bl')
            ->addSelect('bl')
            ->where('bl.budget = :budget')
            ->andWhere('t.deleted_at IS NOT NULL')
            ->setParameter('budget', $budget)
            ->orderBy('t.deleted_at', 'DESC')
            ->getQuery()
            ->getResult();

        $this->getEntityManager()->getFilters()->enable('soft_delete');

        return $results;
    }
}
