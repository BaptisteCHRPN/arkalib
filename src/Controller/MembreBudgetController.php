<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Form\BudgetType;
use App\Repository\BudgetRepository;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class MembreBudgetController extends AbstractController
{
    #[Route('/membre/budget', name: 'app_membre_budget')]
    public function index(BudgetRepository $budgetRepository): Response
    {
       

        return $this->render('membre_budget/index.html.twig');
    }

    #[Route('/new', name: 'app_member_budget_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $budget = new Budget();
        $form = $this->createForm(BudgetType::class, $budget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($budget);
            $entityManager->flush();

            return $this->redirectToRoute('app_membre_budget', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('budget/new.html.twig', [
            'budget' => $budget,
            'form' => $form,
        ]);
    }
}
