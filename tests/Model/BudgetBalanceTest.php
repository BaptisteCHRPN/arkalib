<?php

namespace App\Tests\Model;

use App\Model\BudgetBalance;
use PHPUnit\Framework\TestCase;

final class BudgetBalanceTest extends TestCase
{
    public function testAvailableAppliesTheFullFormula(): void
    {
        // 2 000 € encaissés, 300 € décaissés, 500 € reçus, 1 200 € donnés
        $balance = new BudgetBalance(200000, 30000, 50000, 120000);

        $this->assertSame(100000, $balance->available());
    }

    public function testRealizedBalanceIgnoresReallocations(): void
    {
        $balance = new BudgetBalance(200000, 30000, 50000, 120000);

        $this->assertSame(170000, $balance->realizedBalance());
    }

    public function testAvailableCanBeNegative(): void
    {
        // Tout a été réaffecté, puis une dépense de 500 € est arrivée
        $balance = new BudgetBalance(200000, 50000, 0, 200000);

        $this->assertSame(-50000, $balance->available());
    }
}
