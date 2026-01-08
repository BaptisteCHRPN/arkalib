<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Form\BudgetType;
use App\Entity\Organization;
use App\Repository\BudgetRepository;
use App\Repository\BudgetLineRepository;
use App\Service\BudgetCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class MembreBudgetController extends AbstractController
{
    // This methos is not required becaus we can see all budgets that belong in current organization
    // #[Route('/membre/budget', name: 'app_membre_budget')]
    // public function index(BudgetRepository $budgetRepository): Response
    // {
    //     return $this->render('membre/budget/index.html.twig');
    // }

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
            $slug = $slugger->slug($budget->getName())->lower();
            $budget->setSlug($slug);

            $entityManager->persist($budget);
            $entityManager->flush();

            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/budget/new.html.twig', [
            'budget' => $budget,
            'form' => $form,
        ]);
    }

    #[Route('/{organizationSlug}/{budgetSlug}', name: 'app_membre_budget_show', methods: ['GET'])]
    // This method allow to see preview of the current budget
    public function show(
        EntityManagerInterface $entityManager,
        BudgetLineRepository $budgetLineRepository,
        BudgetCalculatorService $budgetCalculatorService,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])]
        Budget $budget, 
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization
        ): Response
    {
        $organization = $budget->getOrganization();

        $incomes = $entityManager->getRepository(BudgetLine::class)->findBy([
            'budget' => $budget,
            'is_expense' => false
        ]);

        $expenses = $entityManager->getRepository(BudgetLine::class)->findBy([
            'budget' => $budget,
            'is_expense' => true
        ]);

        $sumExpenses = $budgetCalculatorService->sumTotalExpenses($budget);
        $sumIncomes = $budgetCalculatorService->SumTotalIncomes($budget);
        $balanceBudget = $budgetCalculatorService->BalanceBudget($budget);

        return $this->render('member/budget/index.html.twig', [
            'budget' => $budget,
            'organization' => $organization,
            'budget_lines' => $budgetLineRepository->findAll(),
            'incomes' => $incomes,
            'expenses' => $expenses,
            'sum_expenses' => $sumExpenses,
            'sum_incomes' => $sumIncomes,
            'balance_budget' => $balanceBudget,
        ]);
    }
}
