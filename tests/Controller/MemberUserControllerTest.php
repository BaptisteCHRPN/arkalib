<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MemberUserControllerTest extends WebTestCase
{
    public function testUserCannotViewAnotherUsersProfile(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->setEmail('owner@example.com');
        $owner->setPassword('not-checked-by-loginUser');
        $entityManager->persist($owner);

        $someoneElse = new User();
        $someoneElse->setEmail('someone-else@example.com');
        $someoneElse->setPassword('not-checked-by-loginUser');
        $entityManager->persist($someoneElse);

        $entityManager->flush();

        $client->loginUser($someoneElse);

        $client->request('GET', '/user/' . $owner->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserCanViewOwnProfile(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('self@example.com');
        $user->setPassword('not-checked-by-loginUser');
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);

        $client->request('GET', '/user/' . $user->getId());

        $this->assertResponseIsSuccessful();
    }
}
