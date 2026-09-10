<?php

namespace App\Controller\Backoffice;

use App\Entity\Invitation;
use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Repository\OrganizationMembershipRepository;
use App\Repository\OrganizationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annuaire des organisations, en lecture seule.
 *
 * Le cycle de vie complet vit côté membre — créer, modifier, changer le logo,
 * supprimer — et ROLE_ADMIN y accède à toute organisation. Les versions qui
 * vivaient ici étaient de surcroît moins sûres : la création ne générait pas
 * le slug pourtant NOT NULL et unique, et la suppression ne désactivait pas le
 * filtre soft_delete, laissant les budgets en corbeille hors du cascade là où
 * la contrainte d'intégrité les rattrapait.
 */
#[Route('/admin/organization')]
final class AdminOrganizationController extends AbstractController
{
    #[Route(name: 'app_admin_organization_index', methods: ['GET'])]
    public function index(Request $request, OrganizationRepository $organizationRepository): Response
    {
        $search = $request->query->getString('q');

        return $this->render('admin/organization/index.html.twig', [
            'organizations' => $organizationRepository->search($search),
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_organization_show', methods: ['GET'])]
    public function show(
        Organization $organization,
        OrganizationMembershipRepository $membershipRepository,
    ): Response {
        $memberships = $organization->getMemberships()->toArray();
        usort(
            $memberships,
            static fn (OrganizationMembership $a, OrganizationMembership $b) => strcasecmp(
                (string) $a->getUser()?->getEmail(),
                (string) $b->getUser()?->getEmail(),
            ),
        );

        $invitations = $organization->getInvitations()->toArray();
        usort(
            $invitations,
            static fn (Invitation $a, Invitation $b) => $b->getCreatedAt() <=> $a->getCreatedAt(),
        );

        return $this->render('admin/organization/show.html.twig', [
            'organization' => $organization,
            'memberships' => $memberships,
            'invitations' => $invitations,
            'adminCount' => $membershipRepository->countAdmins($organization),
        ]);
    }
}
