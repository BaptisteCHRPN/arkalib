<?php

namespace App\Repository;

use App\Entity\AdminActionLog;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminActionLog>
 */
class AdminActionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminActionLog::class);
    }

    /**
     * Le journal complet, du plus récent au plus ancien.
     *
     * @return AdminActionLog[]
     */
    public function findRecent(int $limit = 200): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.actor', 'a')
            ->addSelect('a')
            ->orderBy('l.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Les interventions subies par une organisation donnée. C'est cette liste
     * que l'on montre au client qui demande des comptes.
     *
     * @return AdminActionLog[]
     */
    public function findForOrganization(Organization $organization, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.actor', 'a')
            ->addSelect('a')
            ->andWhere('l.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('l.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
