<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Form\BudgetLineType;
use App\Service\BudgetCalculatorServie;
use App\Repository\BudgetLineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/member/budgetline')]
final class MemberBudgetLineController extends AbstractController
{
    #[Route('/new/{budgetSlug}', name: 'app_budget_line_new', methods: ['GET', 'POST'])]
    public function new(string $budgetSlug, Request $request, EntityManagerInterface $entityManager,
    #[MapEntity(mapping: ['budgetSlug' => 'slug'])]
    Budget $budget,
    ): Response
    {
        $organization = $budget->getOrganization();
        $budget = $entityManager->getRepository(Budget::class)->findOneBy(['slug' => $budgetSlug]);

        if (!$budget) {
            throw $this->createNotFoundException('Budget non trouvé');
        }

        $budgetLine = new BudgetLine();
        $budgetLine->setBudget($budget);
 
        $form = $this->createForm(BudgetLineType::class, $budgetLine, [
            'budget' => $budget
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($budgetLine);
            $entityManager->flush();

            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $budget->getOrganization()->getSlug(),
                'budgetSlug' => $budgetSlug,
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/budget_line/new.html.twig', [
            'budget' => $budget,
            'organization' => $organization,
            'budget_line' => $budgetLine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_budget_line_show', methods: ['GET'])]
    public function show(BudgetLine $budgetLine): Response
    {
        return $this->render('member/budget_line/show.html.twig', [
            'budget_line' => $budgetLine,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_budget_line_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BudgetLine $budgetLine, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BudgetLineType::class, $budgetLine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_budget_line_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/budget_line/edit.html.twig', [
            'budget_line' => $budgetLine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_budget_line_delete', methods: ['POST'])]
    public function delete(Request $request, BudgetLine $budgetLine, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $budgetLine->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($budgetLine);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_budget_line_index', [], Response::HTTP_SEE_OTHER);
    }
}
