<?php

namespace App\Service;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Repository\CategoryRepository;

class CategoryGroupService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {}

    /**
     * @return array<int, array{category: \App\Entity\Category, lines: BudgetLine[], children: array, expenseTotal: float, incomeTotal: float}>
     */
    public function buildGroups(Budget $budget): array
    {
        $groups = [];

        foreach ($this->categoryRepository->findRootCategories($budget) as $category) {
            $directLines = array_values(array_filter(
                $category->getBudgetLines()->toArray(),
                fn(BudgetLine $line) => $line->isActive()
            ));

            $children = [];
            foreach ($category->getSubCategories() as $subCategory) {
                $childLines = array_values(array_filter(
                    $subCategory->getBudgetLines()->toArray(),
                    fn(BudgetLine $line) => $line->isActive()
                ));

                $children[] = [
                    'category' => $subCategory,
                    'lines' => $childLines,
                    'expenseTotal' => $this->sumByType($childLines, true),
                    'incomeTotal' => $this->sumByType($childLines, false),
                ];
            }

            $childrenExpenseTotal = array_sum(array_column($children, 'expenseTotal'));
            $childrenIncomeTotal = array_sum(array_column($children, 'incomeTotal'));

            $groups[] = [
                'category' => $category,
                'lines' => $directLines,
                'children' => $children,
                'expenseTotal' => $this->sumByType($directLines, true) + $childrenExpenseTotal,
                'incomeTotal' => $this->sumByType($directLines, false) + $childrenIncomeTotal,
            ];
        }

        return $groups;
    }

    /**
     * @param BudgetLine[] $lines
     */
    public function sumByType(array $lines, bool $isExpense): float
    {
        return array_sum(array_map(
            fn(BudgetLine $line) => $line->getAmount(),
            array_filter($lines, fn(BudgetLine $line) => $line->isExpense() === $isExpense)
        ));
    }
}
