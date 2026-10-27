<?php

namespace App\Controller\Member;

use App\Entity\Organization;
use App\Entity\Reallocation;
use App\Repository\BudgetRepository;
use App\Repository\ReallocationRepository;
use App\Security\Voter\OrganizationVoter;
use App\Security\Voter\ReallocationVoter;
use App\Service\ReallocationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/organisation/{organizationSlug}/reaffectations/{id}/annuler', name: 'app_member_reallocation_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        int $id,
        Request $request,
        ReallocationRepository $reallocationRepository,
        ReallocationService $reallocationService,
        EntityManagerInterface $entityManager,
    ): Response {
        $reallocation = $this->findForAction($organization, $id, ReallocationVoter::CANCEL, $reallocationRepository);

        if (!$this->isCsrfTokenValid('cancel-reallocation' . $id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré : veuillez réessayer.');
        } elseif ($reallocation->isCancelled()) {
            // Double clic, ou deux onglets : rien à faire.
            $this->addFlash('warning', 'Cette réaffectation était déjà annulée.');
        } elseif ('' === $reason = trim($request->getPayload()->getString('cancellation_reason'))) {
            $this->addFlash('error', 'Indiquez le motif de l\'annulation.');
        } elseif (mb_strlen($reason) > 255) {
            $this->addFlash('error', 'Le motif de l\'annulation ne doit pas dépasser 255 caractères.');
        } else {
            $reallocationService->cancel($reallocation, $this->getUser(), $reason);
            $entityManager->flush();
            $this->addFlash('success', sprintf('La réaffectation %s a été annulée. Elle ne compte plus dans le disponible des budgets.', $reallocation->describe()));
        }

        return $this->redirectToShow($organization, $id);
    }

    #[Route('/organisation/{organizationSlug}/reaffectations/{id}/restaurer', name: 'app_member_reallocation_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        int $id,
        Request $request,
        ReallocationRepository $reallocationRepository,
        ReallocationService $reallocationService,
        EntityManagerInterface $entityManager,
    ): Response {
        $reallocation = $this->findForAction($organization, $id, ReallocationVoter::RESTORE, $reallocationRepository);

        if (!$this->isCsrfTokenValid('restore-reallocation' . $id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré : veuillez réessayer.');
        } elseif (!$reallocation->isCancelled()) {
            $this->addFlash('warning', 'Cette réaffectation était déjà active.');
        } else {
            // Mêmes règles qu'à la saisie : disponible suffisant, budgets ni
            // clôturés ni à la corbeille.
            $violations = $reallocationService->restore($reallocation);

            if (0 === count($violations)) {
                $entityManager->flush();
                $this->addFlash('success', sprintf('La réaffectation %s a été restaurée.', $reallocation->describe()));
            } else {
                foreach ($violations as $violation) {
                    $this->addFlash('error', 'Impossible de restaurer cette réaffectation : ' . $violation->getMessage());
                }
            }
        }

        return $this->redirectToShow($organization, $id);
    }

    /**
     * Mêmes contrôles que show() : organisation d'abord, puis réaffectation
     * cherchée dans cette organisation, puis droit sur l'action.
     */
    private function findForAction(Organization $organization, int $id, string $attribute, ReallocationRepository $reallocationRepository): Reallocation
    {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        $reallocation = $reallocationRepository->findOneForOrganization($id, $organization)
            ?? throw $this->createNotFoundException('Cette réaffectation n\'existe pas dans cette organisation');

        $this->denyAccessUnlessGranted($attribute, $reallocation);

        return $reallocation;
    }

    private function redirectToShow(Organization $organization, int $id): Response
    {
        return $this->redirectToRoute('app_member_reallocation_show', [
            'organizationSlug' => $organization->getSlug(),
            'id' => $id,
        ], Response::HTTP_SEE_OTHER);
    }
}
