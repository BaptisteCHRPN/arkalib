<?php

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberOrganizationControllerTest extends WebTestCase
{
    public function testNonMemberCannotDeleteOrganization(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Autre organisation');
        $organization->setSlug('autre-organisation-test');
        $organization->setIsActive(true);
        $entityManager->persist($organization);

        $outsider = new User();
        $outsider->setEmail('outsider@example.com');
        $outsider->setPassword('not-checked-by-loginUser');
        $entityManager->persist($outsider);

        $entityManager->flush();

        $client->loginUser($outsider);

        $client->request('POST', '/organization/' . $organization->getId());

        $this->assertResponseStatusCodeSame(403);
    }
}
