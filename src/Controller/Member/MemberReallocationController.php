<?php

namespace App\Controller\Member;

use App\Entity\Organization;
use App\Repository\BudgetRepository;
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
}
