<?php

namespace App\Security\Voter;

use App\Entity\Organization;
use App\Entity\Reallocation;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur les réaffectations. Aucune matrice de rôles ici : chaque action
 * est traduite en droit sur l'organisation, et OrganizationVoter tranche.
 *
 * Pour consulter, annuler ou restaurer, l'organisation est lue sur la
 * réaffectation elle-même, jamais dans l'URL.
 */
final class ReallocationVoter extends Voter
{
    /** Consulter une réaffectation (sujet : Reallocation). */
    public const VIEW = 'REALLOCATION_VIEW';

    /** Saisir une réaffectation (sujet : Organization). */
    public const CREATE = 'REALLOCATION_CREATE';

    /** Annuler une réaffectation (sujet : Reallocation). */
    public const CANCEL = 'REALLOCATION_CANCEL';

    /** Restaurer une réaffectation annulée (sujet : Reallocation). */
    public const RESTORE = 'REALLOCATION_RESTORE';

    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE => $subject instanceof Organization,
            self::VIEW, self::CANCEL, self::RESTORE => $subject instanceof Reallocation,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $organization = $subject instanceof Reallocation ? $subject->getOrganization() : $subject;

        if (null === $organization) {
            return false;
        }

        return $this->accessDecisionManager->decide($token, [$this->organizationAttribute($attribute)], $organization);
    }

    /**
     * Option A (2026-10-26) : le trésorier saisit, annule et restaure ; tout
     * membre consulte. Réserver l'annulation à l'admin ne changerait que cette
     * correspondance.
     */
    private function organizationAttribute(string $attribute): string
    {
        return match ($attribute) {
            self::VIEW => OrganizationVoter::VIEW,
            self::CREATE, self::CANCEL, self::RESTORE => OrganizationVoter::CONTRIBUTE,
        };
    }
}
