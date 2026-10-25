<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\ReallocationLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReallocationLine>
 */
class ReallocationLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReallocationLine::class);
    }

    /**
     * Total des réaffectations non annulées reçues par ce budget.
     */
    public function sumReceivedBy(Budget $budget): ?string
    {
        return $this->sumActive(
            $this->createQueryBuilder('rl')
                ->join('rl.reallocation', 'r')
                ->where('rl.targetBudget = :budget'),
            $budget,
        );
    }

    /**
     * Total des réaffectations non annulées données par ce budget.
     */
    public function sumGivenBy(Budget $budget): ?string
    {
        return $this->sumActive(
            $this->createQueryBuilder('rl')
                ->join('rl.reallocation', 'r')
                ->where('r.sourceBudget = :budget'),
            $budget,
        );
    }

    /**
     * La condition sur deleted_at double volontairement le filtre soft_delete :
     * une réaffectation annulée ne compte jamais, même filtre désactivé.
     */
    private function sumActive(QueryBuilder $qb, Budget $budget): ?string
    {
        $sum = $qb
            ->select('SUM(rl.amount)')
            ->andWhere('r.deleted_at IS NULL')
            ->setParameter('budget', $budget)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $sum ? null : (string) $sum;
    }
}
