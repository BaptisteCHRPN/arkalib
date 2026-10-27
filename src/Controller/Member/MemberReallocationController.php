<?php

namespace App\Controller\Member;

use App\Entity\Organization;
use App\Repository\BudgetRepository;
use App\Repository\ReallocationRepository;
use App\Security\Voter\OrganizationVoter;
use App\Security\Voter\ReallocationVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MemberReallocationController extends AbstractController
{
    /**
     * La saisie elle-même se fait dans le LiveComponent ReallocationForm.
     */
    #[Route('/budget/{organizationSlug}/{budgetSlug}/reaffectations/nouvelle', name: 'app_member_reallocation_new', methods: ['GET'])]
    public function new(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        string $budgetSlug,
        BudgetRepository $budgetRepository,
    ): Response {
        $this->denyAccessUnlessGranted(ReallocationVoter::CREATE, $organization);

        // Slug + organisation : un slug n'est unique qu'au sein d'une organisation.
        $budget = $budgetRepository->findOneBy(['slug' => $budgetSlug, 'organization' => $organization])
            ?? throw $this->createNotFoundException('Le budget demandé n\'existe pas dans cette organisation');

        return $this->render('member/reallocation/new.html.twig', [
            'organization' => $organization,
            'budget' => $budget,
        ]);
    }

    /**
     * Annulées comprises : une décision annulée reste une trace de gouvernance.
     */
    #[Route('/organisation/{organizationSlug}/reaffectations', name: 'app_member_reallocation_index', methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        ReallocationRepository $reallocationRepository,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        return $this->render('member/reallocation/index.html.twig', [
            'organization' => $organization,
            'reallocations' => $reallocationRepository->findForOrganization($organization),
        ]);
    }

    #[Route('/organisation/{organizationSlug}/reaffectations/{id}', name: 'app_member_reallocation_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        int $id,
        ReallocationRepository $reallocationRepository,
    ): Response {
        // D'abord l'organisation : un non-membre reçoit un 403 que
        // l'identifiant existe ou non.
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        // Cherchée dans l'organisation de l'URL : une réaffectation d'une
        // autre organisation donne un 404, sans révéler qu'elle existe.
        $reallocation = $reallocationRepository->findOneForOrganization($id, $organization)
            ?? throw $this->createNotFoundException('Cette réaffectation n\'existe pas dans cette organisation');

        $this->denyAccessUnlessGranted(ReallocationVoter::VIEW, $reallocation);

        return $this->render('member/reallocation/show.html.twig', [
            'organization' => $organization,
            'reallocation' => $reallocation,
        ]);
    }
}
