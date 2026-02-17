<?php

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }
    
    public function findPendingByEmailAndOrga(string $email, Organization $org): ?Invitation
    {
        return $this->createQueryBuilder('i')
            ->where('i.email = :email')
            ->andWhere('i.organisation = :org')
            ->andWhere('i.status = :status')
            ->andWhere('i.expires_at > :now')
            ->setParameter('email', $email)
            ->setParameter('org', $org)
            ->setParameter('status', Invitation::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
