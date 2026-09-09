<?php

namespace App\Tests\Enum;

use App\Enum\OrganizationRole;
use PHPUnit\Framework\TestCase;

final class OrganizationRoleTest extends TestCase
{
    public function testAdminIncludesEveryOtherRole(): void
    {
        $this->assertTrue(OrganizationRole::ADMIN->includes(OrganizationRole::ADMIN));
        $this->assertTrue(OrganizationRole::ADMIN->includes(OrganizationRole::TREASURER));
        $this->assertTrue(OrganizationRole::ADMIN->includes(OrganizationRole::READER));
    }

    public function testTreasurerIncludesReaderButNotAdmin(): void
    {
        $this->assertTrue(OrganizationRole::TREASURER->includes(OrganizationRole::READER));
        $this->assertFalse(OrganizationRole::TREASURER->includes(OrganizationRole::ADMIN));
    }

    public function testReaderIncludesNothingElse(): void
    {
        $this->assertTrue(OrganizationRole::READER->includes(OrganizationRole::READER));
        $this->assertFalse(OrganizationRole::READER->includes(OrganizationRole::TREASURER));
        $this->assertFalse(OrganizationRole::READER->includes(OrganizationRole::ADMIN));
    }

    /**
     * Les valeurs stockées en base : les changer casserait les lignes existantes.
     */
    public function testStoredValuesAreStable(): void
    {
        $this->assertSame('admin', OrganizationRole::ADMIN->value);
        $this->assertSame('treasurer', OrganizationRole::TREASURER->value);
        $this->assertSame('reader', OrganizationRole::READER->value);
    }

    public function testEveryRoleHasALabel(): void
    {
        foreach (OrganizationRole::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }
}
