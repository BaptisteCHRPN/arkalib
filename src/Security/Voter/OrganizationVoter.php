<?php

namespace App\Security\Voter;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Les attributs décrivent ce que le point d'appel veut faire, jamais le rôle
 * attendu : c'est ici, et ici seulement, que la matrice de droits est écrite.
 * Redécouper les rôles ne touchera donc aucun contrôleur.
 */
final class OrganizationVoter extends Voter
{
    /** Consulter l'organisation et ses données : tout membre. */
    public const VIEW = 'ORGANIZATION_VIEW';

    /**
     * Tenir les comptes : tout ce qui est réversible. Le cycle de vie complet
     * des budgets (créer, dupliquer, renommer, clôturer, mettre à la corbeille
     * et restaurer), les lignes, les catégories et les transactions.
     */
    public const CONTRIBUTE = 'ORGANIZATION_CONTRIBUTE';

    /**
     * Administrer : ce qui touche à l'organisation elle-même, à ses membres,
     * ou ce qui ne peut pas être défait (purge de la corbeille).
     */
    public const ADMINISTER = 'ORGANIZATION_ADMINISTER';

    /** Supprimer définitivement l'organisation elle-même. */
    public const DELETE = 'ORGANIZATION_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CONTRIBUTE, self::ADMINISTER, self::DELETE], true)
            && $subject instanceof Organization;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Décision projet : un administrateur global accède à toutes les organisations
        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        /** @var Organization $subject */
        $membership = $subject->getMembershipFor($user);

        if (null === $membership) {
            return false;
        }

        return $membership->getRole()->includes($this->requiredRole($attribute));
    }

    private function requiredRole(string $attribute): OrganizationRole
    {
        return match ($attribute) {
            self::VIEW => OrganizationRole::READER,
            self::CONTRIBUTE => OrganizationRole::TREASURER,
            self::ADMINISTER, self::DELETE => OrganizationRole::ADMIN,
        };
    }
}
