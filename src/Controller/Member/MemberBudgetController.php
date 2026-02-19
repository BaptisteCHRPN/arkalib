<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Form\BudgetType;
use App\Repository\BudgetLineRepository;
use App\Repository\BudgetRepository;
use App\Service\BudgetCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

final class MemberBudgetController extends AbstractController
{   #[IsGranted('ROLE_USER')]
    #[Route('/budget/new/{organizationId}', name: 'app_member_budget_new', methods: ['GET', 'POST'])]
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
            $slug = $slugger->slug(sprintf('%s-%s', $budget->getName(), $organization->getSlug()))->lower();
            $budget->setSlug($slug);

            $entityManager->persist($budget);
            $entityManager->flush();
            $this->addFlash('success', 'Le budget à été créé avec succès !');

            return $this->redirectToRoute('app_organization_budgets', [
                'organizationSlug' => $organization->getSlug()
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/budget/new.html.twig', [
            'budget' => $budget,
            'form' => $form,
            'organization' => $organization,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}', name: 'app_membre_budget_show', methods: ['GET'])]
    public function show(
        BudgetCalculatorService $budgetCalculatorService,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        EntityManagerInterface $entityManager,
        string $budgetSlug
    ): Response {
        $budget = $entityManager->getRepository(Budget::class)->findOneBy([
            'slug' => $budgetSlug,
            'organization' => $organization,
        ]);

        if (!$budget) {
            throw $this->createNotFoundException('Le budget demandé n\'existe pas dans cette organisation');
        }

        if ($budget->getOrganization()->getId() !== $organization->getId()) {
            throw $this->createAccessDeniedException('Ce budget n\'appartient pas à cette organisation');
        }

        // fetching all actives budget  lines
        $expenses = $budget->getBudgetLine()->filter(
            fn($line) => $line->isExpense() && $line->isActive()
        );
        $incomes = $budget->getBudgetLine()->filter(
            fn($line) => !$line->isExpense() && $line->isActive()
        );

        // Calcul toal budget lines active
        $sumExpenses = $budgetCalculatorService->sumTotalExpenses($budget);
        $sumIncomes = $budgetCalculatorService->sumTotalIncomes($budget);
        $balanceBudget = $budgetCalculatorService->balanceBudget($budget);

        // Calcul all total even inactive lines
        $total_expenses_all = 0;
        foreach ($expenses as $expense) {
            $total_expenses_all += $expense->getAmount();
        }

        $total_incomes_all = 0;
        foreach ($incomes as $income) {
            $total_incomes_all += $income->getAmount();
        }

        return $this->render('member/budget/index.html.twig', [
            'budget' => $budget,
            'organization' => $organization,
            'expenses' => $expenses,
            'incomes' => $incomes,
            'sum_expenses' => $sumExpenses,
            'sum_incomes' => $sumIncomes,
            'total_expenses_all' => $total_expenses_all,
            'total_incomes_all' => $total_incomes_all,
            'balance_budget' => $balanceBudget,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/budget-realise/{organizationSlug}/{budgetSlug}', name: 'app_membre_actuel_budget_show', methods: ['GET'])]
    public function showActuelBudget(
        BudgetLineRepository $budgetLineRepository,
        BudgetCalculatorService $budgetCalculatorService,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        EntityManagerInterface $entityManager,
        string $budgetSlug
    ): Response {
        // Récupérer le budget
        $budget = $entityManager->getRepository(Budget::class)->findOneBy([
            'slug' => $budgetSlug,
            'organization' => $organization,
        ]);

        // Récupérer les lignes de budget actives
        $expenses = $budget->getBudgetLine()->filter(
            fn($line) => $line->isExpense() && $line->isActive()
        );

        $incomes = $budget->getBudgetLine()->filter(
            fn($line) => !$line->isExpense() && $line->isActive()
        );

        // Calculs des totaux prévisionnels
        $sumExpenses = $budgetCalculatorService->sumTotalExpenses($budget);
        $sumIncomes = $budgetCalculatorService->sumTotalIncomes($budget);
        $balanceBudget = $budgetCalculatorService->balanceBudget($budget);

        $total_expenses_all = 0;
        foreach ($expenses as $expense) {
            $total_expenses_all += $expense->getAmount();
        }

        $total_incomes_all = 0;
        foreach ($incomes as $income) {
            $total_incomes_all += $income->getAmount();
        }

        // Sommes par catégorie (avec réel)
        $expensesByCategory = $budgetLineRepository->sumExpensesByCategory($budget);
        $incomesByCategory = $budgetLineRepository->sumIncomesByCategory($budget);

        // Calculer les totaux réels
        $sumRealExpenses = 0;
        foreach ($expensesByCategory as $expense) {
            $sumRealExpenses += $expense['real_total'] ?? 0;
        }

        $sumRealIncomes = 0;
        foreach ($incomesByCategory as $income) {
            $sumRealIncomes += $income['real_total'] ?? 0;
        }

        $realBalance = $sumRealIncomes - $sumRealExpenses;

        return $this->render('member/budget/actual_budget.html.twig', [
            'budget' => $budget,
            'organization' => $organization,
            'expenses' => $expenses,
            'incomes' => $incomes,
            'sum_expenses' => $sumExpenses,
            'sum_incomes' => $sumIncomes,
            'sum_real_expenses' => $sumRealExpenses,
            'sum_real_incomes' => $sumRealIncomes,
            'real_balance' => $realBalance,
            'total_expenses_all' => $total_expenses_all,
            'total_incomes_all' => $total_incomes_all,
            'balance_budget' => $balanceBudget,
            'expensesByCategory' => $expensesByCategory,
            'incomesByCategory' => $incomesByCategory,
        ]);
    }
}
