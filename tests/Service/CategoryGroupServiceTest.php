<?php

namespace App\Tests\Service;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\CategoryGroupService;
use PHPUnit\Framework\TestCase;

final class CategoryGroupServiceTest extends TestCase
{
    private function makeBudgetLine(bool $isExpense, float $amount, bool $isActive = true): BudgetLine
    {
        return (new BudgetLine())
            ->setName('Ligne')
            ->setIsExpense($isExpense)
            ->setAmount($amount)
            ->setIsActive($isActive);
    }

    public function testBuildGroupsComputesTotalsForDirectLines(): void
    {
        $budget = new Budget();

        $category = new Category();
        $category->addBudgetLine($this->makeBudgetLine(true, 50.0));
        $category->addBudgetLine($this->makeBudgetLine(false, 100.0));

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->method('findRootCategories')->with($budget)->willReturn([$category]);

        $service = new CategoryGroupService($categoryRepository);

        $groups = $service->buildGroups($budget);

        $this->assertCount(1, $groups);
        $this->assertSame($category, $groups[0]['category']);
        $this->assertCount(2, $groups[0]['lines']);
        $this->assertSame(50.0, $groups[0]['expenseTotal']);
        $this->assertSame(100.0, $groups[0]['incomeTotal']);
        $this->assertSame([], $groups[0]['children']);
    }

    public function testBuildGroupsAddsSubCategoryTotalsToParent(): void
    {
        $budget = new Budget();

        $subCategory = new Category();
        $subCategory->addBudgetLine($this->makeBudgetLine(true, 20.0));

        $category = new Category();
        $category->addBudgetLine($this->makeBudgetLine(true, 30.0));
        $category->addSubCategory($subCategory);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->method('findRootCategories')->willReturn([$category]);

        $service = new CategoryGroupService($categoryRepository);

        $groups = $service->buildGroups($budget);

        $this->assertSame(50.0, $groups[0]['expenseTotal']); // 30 direct + 20 de la sous-catégorie
        $this->assertCount(1, $groups[0]['children']);
        $this->assertSame(20.0, $groups[0]['children'][0]['expenseTotal']);
    }

    public function testBuildGroupsIgnoresInactiveLines(): void
    {
        $budget = new Budget();

        $category = new Category();
        $category->addBudgetLine($this->makeBudgetLine(true, 50.0));
        $category->addBudgetLine($this->makeBudgetLine(true, 999.0, isActive: false));

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->method('findRootCategories')->willReturn([$category]);

        $service = new CategoryGroupService($categoryRepository);

        $groups = $service->buildGroups($budget);

        $this->assertCount(1, $groups[0]['lines']);
        $this->assertSame(50.0, $groups[0]['expenseTotal']);
    }

    public function testSumByTypeSumsOnlyMatchingType(): void
    {
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $service = new CategoryGroupService($categoryRepository);

        $lines = [
            $this->makeBudgetLine(true, 40.0),
            $this->makeBudgetLine(false, 100.0),
            $this->makeBudgetLine(true, 10.0),
        ];

        $this->assertSame(50.0, $service->sumByType($lines, true));
        $this->assertSame(100.0, $service->sumByType($lines, false));
    }
}
