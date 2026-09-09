<?php

namespace App\Controller\Member;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Enum\OrganizationRole;
use App\Security\Voter\OrganizationVoter;
use App\Service\MembershipService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MemberMembershipController extends AbstractController
{
    /**
     * Consulter la liste des membres est ouvert à tous les membres : la page
     * d'accueil de l'organisation les affiche déjà. Seules les actions
     * réclament ADMINISTER.
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/membres', name: 'app_member_membership_list', methods: ['GET'])]
    public function list(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        return $this->render('member/membership/list.html.twig', [
            'organization' => $organization,
            'memberships' => $organization->getMemberships(),
            'roles' => OrganizationRole::cases(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/membres/{id}/role', name: 'app_member_membership_change_role', methods: ['POST'])]
    public function changeRole(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        OrganizationMembership $membership,
        Request $request,
        MembershipService $membershipService,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::ADMINISTER, $organization);
        $this->assertMembershipBelongsTo($membership, $organization);

        if (!$this->isCsrfTokenValid('membership_role_' . $membership->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $role = OrganizationRole::tryFrom($request->request->getString('role'));

        if (null === $role) {
            $this->addFlash('error', 'Rôle inconnu.');

            return $this->redirectToMembers($organization);
        }

        try {
            $membershipService->changeRole($membership, $role);
            $this->addFlash('success', 'Le rôle a été modifié.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToMembers($organization);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/membres/{id}/retirer', name: 'app_member_membership_remove', methods: ['POST'])]
    public function remove(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        OrganizationMembership $membership,
        Request $request,
        MembershipService $membershipService,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::ADMINISTER, $organization);
        $this->assertMembershipBelongsTo($membership, $organization);

        if (!$this->isCsrfTokenValid('membership_remove_' . $membership->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Se retirer soi-même par ce chemin ferait perdre l'accès à la page en
        // cours de route : c'est « Quitter l'organisation » qui gère ce cas.
        if ($membership->getUser() === $this->getUser()) {
            $this->addFlash('error', 'Pour vous retirer vous-même, utilisez « Quitter l\'organisation ».');

            return $this->redirectToMembers($organization);
        }

        try {
            $membershipService->remove($membership);
            $this->addFlash('success', 'Le membre a été retiré de l\'organisation.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToMembers($organization);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/membres/quitter', name: 'app_member_membership_leave', methods: ['POST'])]
    public function leave(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        Request $request,
        MembershipService $membershipService,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        if (!$this->isCsrfTokenValid('membership_leave_' . $organization->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        try {
            $membershipService->leave($organization, $this->getUser());
            $this->addFlash('success', sprintf('Vous avez quitté « %s ».', $organization->getName()));

            return $this->redirectToRoute('app_dashboard');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToMembers($organization);
        }
    }

    /**
     * Le slug de l'URL et l'identifiant de l'appartenance sont résolus
     * indépendamment : rien ne garantit que l'appartenance relève bien de
     * cette organisation.
     */
    private function assertMembershipBelongsTo(OrganizationMembership $membership, Organization $organization): void
    {
        if ($membership->getOrganization() !== $organization) {
            throw $this->createNotFoundException();
        }
    }

    private function redirectToMembers(Organization $organization): Response
    {
        return $this->redirectToRoute('app_member_membership_list', [
            'organizationSlug' => $organization->getSlug(),
        ], Response::HTTP_SEE_OTHER);
    }
}
