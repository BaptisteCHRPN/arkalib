<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserCheckerTest extends TestCase
{
    public function testUnverifiedUserCannotAuthenticate(): void
    {
        $user = new User();
        $user->setEmail('pas-verifie@example.com');
        $user->setIsVerified(false);

        $checker = new UserChecker();

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessageMatches('/n\'est pas encore vérifié/');

        $checker->checkPreAuth($user);
    }

    public function testVerifiedUserCanAuthenticate(): void
    {
        $user = new User();
        $user->setEmail('verifie@example.com');
        $user->setIsVerified(true);

        $checker = new UserChecker();

        $checker->checkPreAuth($user);

        // checkPreAuth() ne retourne rien : le seul comportement observable est
        // « aucune exception levée ». On l'affirme explicitement pour que le test
        // ne soit pas considéré comme « risqué » (sans assertion) par PHPUnit.
        $this->expectNotToPerformAssertions();
    }

    public function testCheckerIgnoresUsersThatAreNotAppUsers(): void
    {
        // Un utilisateur d'un autre provider (ex. in_memory) n'a pas de notion
        // de vérification d'email : le checker doit le laisser passer.
        $checker = new UserChecker();

        $checker->checkPreAuth(new InMemoryUser('autre@example.com', null));

        $this->expectNotToPerformAssertions();
    }

    public function testCheckPostAuthDoesNothing(): void
    {
        $user = new User();
        $user->setIsVerified(false);

        $checker = new UserChecker();

        // Même un compte non vérifié ne doit pas être bloqué en post-auth :
        // toute la logique est dans checkPreAuth().
        $checker->checkPostAuth($user);

        $this->expectNotToPerformAssertions();
    }
}
