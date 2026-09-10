<?php

namespace App\Service;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les chiffres du tableau de bord d'administration.
 *
 * Tout est compté en base : la page affichait auparavant six liens statiques
 * au prix de six findAll() complets, dont le gabarit n'utilisait rien.
 *
 * Les compteurs de budgets, lignes, catégories et transactions passent par le
 * filtre soft_delete, donc excluent la corbeille — c'est bien ce qu'on veut
 * afficher comme « existant ».
 */
class BackOfficeDashboard
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'organizations' => $this->count(Organization::class),
            'inactiveOrganizations' => $this->count(Organization::class, ['is_active' => false]),
            'users' => $this->count(User::class),
            'unverifiedUsers' => $this->count(User::class, ['isVerified' => false]),
            'neverLoggedIn' => $this->count(User::class, ['lastLoginAt' => null]),
            'budgets' => $this->count(Budget::class),
            'closedBudgets' => $this->count(Budget::class, ['is_closed' => true]),
            'budgetLines' => $this->count(BudgetLine::class),
            'categories' => $this->count(Category::class),
            'transactions' => $this->count(Transaction::class),
        ];
    }

    /**
     * Les organisations qui n'ont plus d'administrateur : personne ne peut plus
     * y inviter, changer un rôle ni modifier l'organisation. C'est l'anomalie
     * qui remonte le plus souvent en réclamation.
     *
     * @return Organization[]
     */
    public function organizationsWithoutAdmin(): array
    {
        return $this->em->createQuery(
            'SELECT o FROM ' . Organization::class . ' o
             WHERE NOT EXISTS (
                SELECT m.id FROM ' . \App\Entity\OrganizationMembership::class . ' m
                WHERE m.organization = o AND m.role = :admin
             )
             ORDER BY o.name ASC'
        )
            ->setParameter('admin', OrganizationRole::ADMIN)
            ->getResult();
    }

    /**
     * Invitations restées « en attente » alors que leur délai est écoulé :
     * l'invité croit avoir un lien valide, il ne l'a plus.
     */
    public function countStaleInvitations(): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(i.id) FROM ' . Invitation::class . ' i
             WHERE i.status = :pending AND i.expires_at < :now'
        )
            ->setParameter('pending', Invitation::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getSingleScalarResult();
    }

    /**
     * @param class-string $entity
     * @param array<string, mixed> $criteria
     */
    private function count(string $entity, array $criteria = []): int
    {
        return $this->em->getRepository($entity)->count($criteria);
    }
}
