<?php

namespace App\Tests\Service;

use App\Entity\Budget;
use App\Entity\User;
use App\Service\SoftDeleteService;
use PHPUnit\Framework\TestCase;

final class SoftDeleteServiceTest extends TestCase
{
    public function testSoftDeleteStampsDateAndAuthor(): void
    {
        $budget = new Budget();
        $user = new User();

        $service = new SoftDeleteService();
        $service->softDelete($budget, $user);

        $this->assertNotNull($budget->getDeletedAt());
        $this->assertSame($user, $budget->getDeletedBy());
    }

    public function testRestoreClearsDateAndAuthor(): void
    {
        $budget = new Budget();
        $user = new User();

        $service = new SoftDeleteService();
        $service->softDelete($budget, $user);
        $service->restore($budget);

        $this->assertNull($budget->getDeletedAt());
        $this->assertNull($budget->getDeletedBy());
    }

    public function testSoftDeleteRejectsEntityWithoutSoftDeleteTrait(): void
    {
        $service = new SoftDeleteService();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/n\'utilise pas SoftDeleteTrait/');

        $service->softDelete(new \stdClass(), new User());
    }

    public function testRestoreRejectsEntityWithoutSoftDeleteTrait(): void
    {
        $service = new SoftDeleteService();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/n\'utilise pas SoftDeleteTrait/');

        $service->restore(new \stdClass());
    }
}
