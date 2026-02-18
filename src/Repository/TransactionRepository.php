<?php

namespace App\Repository;

use App\Entity\Budget;
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
}
