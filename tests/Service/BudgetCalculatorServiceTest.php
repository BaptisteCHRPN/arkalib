<?php

namespace App\Tests\Service;

use App\Entity\Budget;
use App\Repository\BudgetLineRepository;
use App\Service\BudgetCalculatorService;
use PHPUnit\Framework\TestCase;

final class BudgetCalculatorServiceTest extends TestCase
{
    public function testSumTotalExpensesDelegatesToRepository(): void
    {
        $budget = new Budget();
        $repository = $this->createMock(BudgetLineRepository::class);
        $repository->method('sumExpensesBudget')->with($budget)->willReturn(300.0);

        $service = new BudgetCalculatorService($repository);

        $this->assertSame(300.0, $service->SumTotalExpenses($budget));
    }

    public function testSumTotalIncomesDelegatesToRepository(): void
    {
        $budget = new Budget();
        $repository = $this->createMock(BudgetLineRepository::class);
        $repository->method('sumIncomesBudget')->with($budget)->willReturn(1000.0);

        $service = new BudgetCalculatorService($repository);

        $this->assertSame(1000.0, $service->SumTotalIncomes($budget));
    }

    public function testBalanceBudgetIsIncomesMinusExpenses(): void
    {
        $budget = new Budget();
        $repository = $this->createMock(BudgetLineRepository::class);
        $repository->method('sumIncomesBudget')->willReturn(1000.0);
        $repository->method('sumExpensesBudget')->willReturn(300.0);

        $service = new BudgetCalculatorService($repository);

        $this->assertSame(700.0, $service->BalanceBudget($budget));
    }
}
