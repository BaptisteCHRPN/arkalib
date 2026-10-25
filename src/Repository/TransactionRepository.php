<?php

namespace App\Repository;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Repository\Trait\SearchesTextFieldsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    use SearchesTextFieldsTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Recherche une transaction par référence, commentaire ou moyen de
     * paiement. Pas de recherche par organisation ici : une transaction n'y est
     * reliée que par ses lignes, et une jointure ManyToMany multiplierait les
     * résultats.
     *
     * @return Transaction[]
     */
    public function search(?string $term): array
    {
        return $this->searchQueryBuilder($term, ['reference', 'comment', 'payment_method'], 't')
            ->getQuery()
            ->getResult();
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

    /**
     * Transactions d'un budget, chacune une seule fois, avec toutes leurs
     * lignes chargées : Transaction::getDirection() en a besoin.
     *
     * On ne filtre pas directement la jointure sur le budget : Doctrine
     * hydraterait alors des collections partielles. La sous-requête choisit
     * les transactions, la requête principale charge leurs lignes.
     *
     * Les transactions et lignes à la corbeille sont exclues par le filtre
     * soft_delete.
     *
     * @return Transaction[]
     */
    public function findRealizedByBudget(Budget $budget): array
    {
        $transactionsOfBudget = $this->createQueryBuilder('t2')
            ->select('t2.id')
            ->join('t2.budget_line', 'bl2')
            ->where('bl2.budget = :budget');

        $qb = $this->createQueryBuilder('t');

        return $qb
            ->addSelect('bl')
            ->join('t.budget_line', 'bl')
            ->where($qb->expr()->in('t.id', $transactionsOfBudget->getDQL()))
            ->setParameter('budget', $budget)
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
