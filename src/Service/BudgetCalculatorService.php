<?php

namespace App\Service;

use App\Entity\Budget;
use App\Repository\BudgetLineRepository;

class BudgetCalculatorService {
    public function __construct(
        private BudgetLineRepository $budgetLineRepository
    ) {}

    public function SumTotalExpenses(Budget $budget): float
    {
        return $this->budgetLineRepository->sumExpensesBudget($budget);
    }

    public function SumTotalIncomes(Budget $budget): float
    {
        return $this->budgetLineRepository->sumIncomesBudget($budget);
    }

    public function BalanceBudget(Budget $budget): float
    {
            $incomes = $this->budgetLineRepository->sumIncomesBudget($budget);
            $expenses =  $this->budgetLineRepository->sumExpensesBudget($budget);

            return $incomes - $expenses;
    }



}