<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Form\BudgetType;
use App\Repository\BudgetLineRepository;
use App\Security\AssertsOwnershipTrait;
use App\Security\Voter\OrganizationVoter;
use App\Service\BudgetCalculatorService;
use App\Service\CategoryGroupService;
use App\Service\SoftDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

final class MemberBudgetController extends AbstractController
{
    use AssertsOwnershipTrait;

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/new/{organizationId}', name: 'app_member_budget_new', methods: ['GET', 'POST'])]
    public function new(
        int $organizationId,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $organization = $entityManager->getRepository(Organization::class)->find($organizationId);

        if (!$organization) {
            throw $this->createNotFoundException('Organisation non trouvée');
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);

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
        CategoryGroupService $categoryGroupService,
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

        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);
        $this->assertBudgetBelongsToOrganization($budget, $organization);

        $categoryGroups = $categoryGroupService->buildGroups($budget);

        $uncategorizedLines = array_values(array_filter(
            $budget->getBudgetLine()->toArray(),
            fn(BudgetLine $line) => $line->getCategory() === null && $line->isActive()
        ));

        $sumExpenses = $budgetCalculatorService->sumTotalExpenses($budget);
        $sumIncomes = $budgetCalculatorService->sumTotalIncomes($budget);
        $balanceBudget = $budgetCalculatorService->balanceBudget($budget);

        return $this->render('member/budget/index.html.twig', [
            'budget' => $budget,
            'organization' => $organization,
            'categoryGroups' => $categoryGroups,
            'uncategorizedLines' => $uncategorizedLines,
            'uncategorizedExpenseTotal' => $categoryGroupService->sumByType($uncategorizedLines, true),
            'uncategorizedIncomeTotal' => $categoryGroupService->sumByType($uncategorizedLines, false),
            'sumExpenses' => $sumExpenses,
            'sumIncomes' => $sumIncomes,
            'balanceBudget' => $balanceBudget,
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

        if (!$budget) {
            throw $this->createNotFoundException('Le budget demandé n\'existe pas dans cette organisation');
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);
        $this->assertBudgetBelongsToOrganization($budget, $organization);

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

        $totalExpensesAll = 0;
        foreach ($expenses as $expense) {
            $totalExpensesAll += $expense->getAmount();
        }

        $totalIncomesAll = 0;
        foreach ($incomes as $income) {
            $totalIncomesAll += $income->getAmount();
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
            'sumExpenses' => $sumExpenses,
            'sumIncomes' => $sumIncomes,
            'sumRealExpenses' => $sumRealExpenses,
            'sumRealIncomes' => $sumRealIncomes,
            'realBalance' => $realBalance,
            'totalExpensesAll' => $totalExpensesAll,
            'totalIncomesAll' => $totalIncomesAll,
            'balanceBudget' => $balanceBudget,
            'expensesByCategory' => $expensesByCategory,
            'incomesByCategory' => $incomesByCategory,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/close', name: 'app_member_budget_close', methods: ['POST'])]
    public function closeBudget(
        Request $request,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        string $budgetSlug
    ): Response {
        $budget = $entityManager->getRepository(Budget::class)->findOneBy([
            'slug' => $budgetSlug,
            'organization' => $organization,
        ]);

        if (!$budget) {
            throw $this->createNotFoundException('Budget non trouvé');
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);

        if ($this->isCsrfTokenValid('close' . $budget->getId(), $request->getPayload()->getString('_token'))) {
            $budget->setIsClosed(true);
            $entityManager->flush();
            $this->addFlash('success', 'Le budget a été clôturé. Il est maintenant en lecture seule.');
        }

        return $this->redirectToRoute('app_membre_budget_show', [
            'organizationSlug' => $organization->getSlug(),
            'budgetSlug' => $budget->getSlug(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/reopen', name: 'app_member_budget_reopen', methods: ['POST'])]
    public function reopenBudget(
        Request $request,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        string $budgetSlug
    ): Response {
        $budget = $entityManager->getRepository(Budget::class)->findOneBy([
            'slug' => $budgetSlug,
            'organization' => $organization,
        ]);

        if (!$budget) {
            throw $this->createNotFoundException('Budget non trouvé');
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);

        if ($this->isCsrfTokenValid('reopen' . $budget->getId(), $request->getPayload()->getString('_token'))) {
            $budget->setIsClosed(false);
            $entityManager->flush();
            $this->addFlash('success', 'Le budget a été réouvert. Les modifications sont à nouveau possibles.');
        }

        return $this->redirectToRoute('app_membre_budget_show', [
            'organizationSlug' => $organization->getSlug(),
            'budgetSlug' => $budget->getSlug(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/delete', name: 'app_member_budget_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        SoftDeleteService $softDeleteService,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);
        $this->assertBudgetBelongsToOrganization($budget, $organization);

        if ($this->isCsrfTokenValid('delete' . $budget->getId(), $request->getPayload()->getString('_token'))) {
            $softDeleteService->softDelete($budget, $this->getUser());
            $entityManager->flush();
            $this->addFlash('success', 'Le budget a été déplacé dans la corbeille.');
        }

        return $this->redirectToRoute('app_organization_budgets', [
            'organizationSlug' => $organization->getSlug(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/duplication-budget/{organizationSlug}/{budgetSlug}', name: 'app_membre_duplicate_budget', methods: ['GET', 'POST'])]
    public function duplicateBudget(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        string $budgetSlug
    ): Response {
        $budget = $entityManager->getRepository(Budget::class)->findOneBy([
            'slug' => $budgetSlug,
            'organization' => $organization,
        ]);

        if (!$budget) {
            throw $this->createNotFoundException('Le budget demandé n\'existe pas');
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $organization);

        // On pré-remplit le nouveau budget avec les valeurs de l'original
        $newBudget = new Budget();
        $newBudget->setName('Copie de ' . $budget->getName());
        $newBudget->setStartDate($budget->getStartDate());
        $newBudget->setEndDate($budget->getEndDate());
        $newBudget->setOrganization($organization);
        $newBudget->setIsActive(true);
        $newBudget->setIsClosed(false);

        $form = $this->createForm(BudgetType::class, $newBudget);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug(sprintf('%s-%s', $newBudget->getName(), $organization->getSlug()))->lower();
            $newBudget->setSlug($slug);

            foreach ($budget->getBudgetLine() as $line) {
                $newLine = new BudgetLine();
                $newLine->setName($line->getName());
                $newLine->setAmount($line->getAmount());
                $newLine->setIsExpense($line->isExpense());
                $newLine->setIsActive($line->isActive());
                $newLine->setCategory($line->getCategory());
                $newLine->setBudget($newBudget);
                $entityManager->persist($newLine);
            }

            $entityManager->persist($newBudget);
            $entityManager->flush();

            $this->addFlash('success', 'Le budget a été dupliqué avec succès !');

            return $this->redirectToRoute('app_organization_budgets', [
                'organizationSlug' => $organization->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/budget/duplicate.html.twig', [
            'form' => $form,
            'budget' => $budget,
            'organization' => $organization,
        ]);
    }
}
