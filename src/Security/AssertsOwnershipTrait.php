<?php

namespace App\Security;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Category;
use App\Entity\Organization;
use App\Entity\Transaction;

trait AssertsOwnershipTrait
{
    private function assertBudgetBelongsToOrganization(Budget $budget, Organization $organization): void
    {
        if ($budget->getOrganization() !== $organization) {
            throw $this->createNotFoundException();
        }
    }

    private function assertEntityBelongsToBudget(BudgetLine|Category $entity, Budget $budget): void
    {
        if ($entity->getBudget() !== $budget) {
            throw $this->createNotFoundException();
        }
    }

    private function assertTransactionBelongsToBudget(Transaction $transaction, Budget $budget): void
    {
        if (!$transaction->getBudgetLine()->exists(fn (int $i, BudgetLine $line) => $line->getBudget() === $budget)) {
            throw $this->createNotFoundException();
        }
    }
}
