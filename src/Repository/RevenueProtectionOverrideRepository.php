<?php

namespace App\Repository;

use App\Entity\RevenueProtectionOverride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RevenueProtectionOverride>
 */
class RevenueProtectionOverrideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RevenueProtectionOverride::class);
    }
}
