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

    public function testMemberCanDeleteOrganization(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $organization = new Organization();
        $organization->setName('Mon organisation');
        $organization->setSlug('mon-organisation-test');
        $organization->setIsActive(true);

        $member = new User();
        $member->setEmail('membre@example.com');
        $member->setPassword('not-checked-by-loginUser');

        $organization->addUser($member);

        $entityManager->persist($organization);
        $entityManager->persist($member);
        $entityManager->flush();

        $client->loginUser($member);

        $crawler = $client->request('GET', '/' . $organization->getSlug() . '/edit');
        $token = $crawler->filter('#deleteOrganizationModal input[name="_token"]')->attr('value');

        $client->request('POST', '/organization/' . $organization->getId(), [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/dashboard');
    }
}
