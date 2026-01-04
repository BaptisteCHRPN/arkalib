<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Form\BudgetType;
use App\Entity\Organization;
use App\Repository\BudgetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class MembreBudgetController extends AbstractController
{
    #[Route('/membre/budget', name: 'app_membre_budget')]
    public function index(BudgetRepository $budgetRepository): Response
    {
        return $this->render('membre/budget/index.html.twig');
    }

    #[Route('/membre/budget/new/{organizationId}', name: 'app_member_budget_new', methods: ['GET', 'POST'])]
    public function new(int $organizationId, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $organization = $entityManager->getRepository(Organization::class)->find($organizationId);

        if (!$organization) {
            throw $this->createNotFoundException('Organisation non trouvée');
        }

        if (!$organization->getUsers()->contains($this->getUser())) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas membre de cette organisation');
        }

        $budget = new Budget();
        $budget->setOrganization($organization);

        $form = $this->createForm(BudgetType::class, $budget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // auto slug generation
            $slug = $slugger->slug($organization->getName())->lower();
            $organization->setSlug($slug);

            $entityManager->persist($budget);
            $entityManager->flush();

            return $this->redirectToRoute('app_organization_budgets', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/budget/new.html.twig', [
            'budget' => $budget,
            'form' => $form,
        ]);
    }

    #[Route('/membre/budget/{slug}', name: 'app_membre_budget_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Budget $budget): Response
    {
        $organization = $budget->getOrganization();

        return $this->render('member/budget/index.html.twig', [
            'budget' => $budget,
            'organization' => $organization,
        ]);
    }
}
