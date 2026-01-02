<?php

namespace App\Controller\Backoffice;

use App\Entity\BudgetLine;
use App\Form\BudgetLineType;
use App\Repository\BudgetLineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/budgetline')]
final class AdminBudgetLineController extends AbstractController
{
    #[Route(name: 'app_admin_budget_line_index', methods: ['GET'])]
    public function index(BudgetLineRepository $budgetLineRepository): Response
    {
        return $this->render('admin/budget_line/index.html.twig', [
            'budget_lines' => $budgetLineRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_budget_line_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $budgetLine = new BudgetLine();
        $form = $this->createForm(BudgetLineType::class, $budgetLine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($budgetLine);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_budget_line_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/budget_line/new.html.twig', [
            'budget_line' => $budgetLine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_budget_line_show', methods: ['GET'])]
    public function show(BudgetLine $budgetLine): Response
    {
        return $this->render('admin/budget_line/show.html.twig', [
            'budget_line' => $budgetLine,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_budget_line_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BudgetLine $budgetLine, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BudgetLineType::class, $budgetLine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_budget_line_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/budget_line/edit.html.twig', [
            'budget_line' => $budgetLine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_budget_line_delete', methods: ['POST'])]
    public function delete(Request $request, BudgetLine $budgetLine, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$budgetLine->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($budgetLine);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_budget_line_index', [], Response::HTTP_SEE_OTHER);
    }
}
