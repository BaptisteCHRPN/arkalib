<?php

namespace App\DataFixtures;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $organization = new Organization();
        $organization->setName('Arkalib');
        $organization->setSlug('arkalib');
        $organization->setDescription('Organisation de démonstration');
        $manager->persist($organization);

        $user = new User();
        $user->setEmail('baptiste@arkalib.fr');
        $user->setFirstname('Baptiste');
        $user->setLastname('Cherpin');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Azerty31!?'));
        $user->addOrganization($organization);
        $manager->persist($user);

        $manager->flush();
    }
}
