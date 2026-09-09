<?php

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationMembership>
 */
class OrganizationMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationMembership::class);
    }

    public function findOneByOrganizationAndUser(Organization $organization, User $user): ?OrganizationMembership
    {
        return $this->findOneBy([
            'organization' => $organization,
            'user' => $user,
        ]);
    }

    /**
     * Sert à l'invariant « on ne retire pas le dernier administrateur ».
     */
    public function countAdmins(Organization $organization): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.organization = :organization')
            ->andWhere('m.role = :role')
            ->setParameter('organization', $organization)
            ->setParameter('role', OrganizationRole::ADMIN)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
