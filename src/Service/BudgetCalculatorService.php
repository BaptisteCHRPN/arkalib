<?php

namespace App\Service;

use App\Entity\Budget;
use App\Repository\BudgetLineRepository;

class BudgetCalculatorService {
    public function __construct(
        private BudgetLineRepository $budgetLineRepository
    ) {}

    public function SumTotalExpenses(Budget $budget) :float
    {
        return $this->budgetLineRepository->sumExpensesBudget($budget);
    }


}