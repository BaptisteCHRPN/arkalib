<?php

namespace App\Repository;

use App\Entity\User;
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
     * Recherche un compte par email, prénom ou nom, les trois champs étant
     * concaténés : une réclamation donne souvent « Marie Dupont » d'un bloc,
     * jamais un champ isolé.
     *
     * Un terme vide renvoie tout le monde, ce qui fait de cette méthode le
     * point d'entrée unique de la liste des utilisateurs.
     *
     * @return User[]
     */
    public function search(?string $term): array
    {
        $queryBuilder = $this->createQueryBuilder('u')->orderBy('u.id', 'DESC');

        $term = trim((string) $term);

        if ('' === $term) {
            return $queryBuilder->getQuery()->getResult();
        }

        // Les jokers SQL saisis au clavier sont neutralisés : sans ça, « % »
        // ramènerait toute la table et un email contenant « _ » ramènerait des
        // comptes qui ne lui ressemblent pas. On échappe avec « ! » plutôt
        // qu'avec l'antislash, dont le sens varie entre MySQL et SQLite.
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';

        return $queryBuilder
            ->andWhere("CONCAT(u.email, ' ', COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) LIKE :pattern ESCAPE '!'")
            ->setParameter('pattern', $pattern)
            ->getQuery()
            ->getResult();
    }
}
