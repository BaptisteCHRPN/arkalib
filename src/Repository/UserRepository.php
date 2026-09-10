<?php

namespace App\Repository;

use App\Entity\User;
use App\Repository\Trait\SearchesTextFieldsTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    use SearchesTextFieldsTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmailChangeToken(string $token): ?User
    {
        return $this->findOneBy(['emailChangeToken' => $token]);
    }

    /**
     * Recherche un compte par email, prénom ou nom.
     *
     * @return User[]
     */
    public function search(?string $term): array
    {
        return $this->searchQueryBuilder($term, ['email', 'firstname', 'lastname'], 'u')
            ->getQuery()
            ->getResult();
    }
}
