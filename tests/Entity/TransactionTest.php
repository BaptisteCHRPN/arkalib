<?php

namespace App\Tests\Entity;

use App\Entity\BudgetLine;
use App\Entity\Transaction;
use App\Enum\TransactionDirection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;


final class TransactionTest extends TestCase
{
    public function testTransactionLinkedOnlyToIncomesIsCashIn(): void
    {
        $transaction = $this->transactionLinkedTo(false, false);

        $this->assertSame(TransactionDirection::CASH_IN, $transaction->getDirection());
    }

    public function testTransactionLinkedOnlyToExpensesIsCashOut(): void
    {
        $transaction = $this->transactionLinkedTo(true, true);

        $this->assertSame(TransactionDirection::CASH_OUT, $transaction->getDirection());
    }

    public function testTransactionMixingIncomeAndExpenseHasNoDirection(): void
    {
        $transaction = $this->transactionLinkedTo(false, true);

        $this->assertNull($transaction->getDirection());
    }

    public function testTransactionWithoutLineHasNoDirection(): void
    {
        $this->assertNull((new Transaction())->getDirection());
    }

    public function testMixingIncomeAndExpenseIsRejectedOnBudgetLineField(): void
    {
        $violations = $this->validator()->validate($this->transactionLinkedTo(false, true));

        $this->assertCount(1, $violations);
        $this->assertSame('budget_line', $violations[0]->getPropertyPath());
    }

    public function testSingleDirectionIsAccepted(): void
    {
        $this->assertCount(0, $this->validator()->validate($this->transactionLinkedTo(false, false)));
        $this->assertCount(0, $this->validator()->validate($this->transactionLinkedTo(true, true)));
    }

    /**
     * Le cas « aucune ligne » relève du formulaire, pas de cette règle :
     * on vérifie qu'elle ne produit pas une seconde erreur en doublon.
     */
    public function testTransactionWithoutLineIsNotReportedAsMixed(): void
    {
        $this->assertCount(0, $this->validator()->validate(new Transaction()));
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }


    /**
     * Construit une transaction rattachée à une ligne par booléen fourni
     * (true = dépense, false = recette).
     */
    private function transactionLinkedTo(bool ...$isExpense): Transaction
    {
        $transaction = new Transaction();

        foreach ($isExpense as $expense) {
            $transaction->addBudgetLine((new BudgetLine())->setIsExpense($expense));
        }

        return $transaction;
    }
}
