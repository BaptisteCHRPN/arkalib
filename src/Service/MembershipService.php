<?php

namespace App\Service;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use App\Enum\OrganizationRole;
use App\Repository\OrganizationMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère les appartenances à une organisation.
 *
 * L'invariant « une organisation garde toujours au moins un administrateur »
 * vit ici et nulle part ailleurs : il doit tenir quel que soit le chemin
 * emprunté — retrait d'un membre, rétrogradation, ou départ volontaire.
 */
class MembershipService
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrganizationMembershipRepository $membershipRepository,
    ) {}

    public function changeRole(OrganizationMembership $membership, OrganizationRole $newRole): void
    {
        if ($membership->getRole() === $newRole) {
            return;
        }

        // Promouvoir ne menace jamais l'invariant ; seule une sortie du rôle
        // d'administrateur peut laisser l'organisation sans pilote.
        if (OrganizationRole::ADMIN !== $newRole) {
            $this->assertNotTheLastAdmin($membership);
        }

        $membership->setRole($newRole);
        $this->em->flush();
    }

    public function remove(OrganizationMembership $membership): void
    {
        $this->assertNotTheLastAdmin($membership);

        // Retrait des deux collections pour que les entités restent cohérentes
        // le temps de la requête ; le remove() explicite garantit la suppression
        // en base sans dépendre de la configuration orphanRemoval.
        $membership->getOrganization()?->removeMembership($membership);
        $membership->getUser()?->removeMembership($membership);

        $this->em->remove($membership);
        $this->em->flush();
    }

    public function leave(Organization $organization, User $user): void
    {
        $membership = $organization->getMembershipFor($user);

        if (null === $membership) {
            throw new \LogicException('Vous ne faites pas partie de cette organisation.');
        }

        $this->remove($membership);
    }

    private function assertNotTheLastAdmin(OrganizationMembership $membership): void
    {
        if (!$membership->isAdmin()) {
            return;
        }

        if ($this->membershipRepository->countAdmins($membership->getOrganization()) <= 1) {
            throw new \LogicException(
                "Cette organisation n'aurait plus d'administrateur. Désignez-en un autre avant de continuer."
            );
        }
    }
}
