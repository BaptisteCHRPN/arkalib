<?php

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le prénom n'est obligatoire que parce que ce subscriber l'impose : rien ne le
 * garantit en base, où la colonne reste nullable, et un écran de saisie se
 * quitte en fermant l'onglet. Ce sont donc ces redirections qui portent toute
 * la règle — d'où l'attention portée ici aux quatre exceptions, dont chacune
 * correspond à une façon précise de rendre l'application inutilisable.
 */
final class ProfileCompletionSubscriberTest extends WebTestCase
{
    private function loggedInUser(KernelBrowser $client, ?string $firstname): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('completion@example.com');
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname($firstname);
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);

        return $user;
    }

    public function testAUserWithoutAFirstnameIsSentToTheCompletionScreen(): void
    {
        $client = static::createClient();
        $this->loggedInUser($client, null);

        $client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/profil/completer');
    }

    public function testAUserWithAFirstnameNavigatesFreely(): void
    {
        $client = static::createClient();
        $this->loggedInUser($client, 'Camille');

        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
    }

    /**
     * Un prénom réduit à des espaces ne dit rien de plus qu'un prénom absent.
     */
    public function testABlankFirstnameDoesNotCount(): void
    {
        $client = static::createClient();
        $this->loggedInUser($client, '   ');

        $client->request('GET', '/dashboard');

        $this->assertResponseRedirects('/profil/completer');
    }

    /**
     * Sans cette exception, l'écran se redirigerait vers lui-même jusqu'à ce
     * que le navigateur abandonne.
     */
    public function testTheCompletionScreenItselfIsNotRedirected(): void
    {
        $client = static::createClient();
        $this->loggedInUser($client, null);

        $client->request('GET', '/profil/completer');

        $this->assertResponseIsSuccessful();
    }

    /**
     * On n'enferme pas quelqu'un dans un formulaire sans porte de sortie.
     */
    public function testLoggingOutRemainsPossible(): void
    {
        $client = static::createClient();
        $this->loggedInUser($client, null);

        $client->request('GET', '/logout');

        $this->assertResponseRedirects();
        $this->assertStringNotContainsString(
            '/profil/completer',
            $client->getResponse()->headers->get('Location') ?? '',
        );
    }

    /**
     * Rediriger une 404 masquerait l'erreur derrière un écran sans rapport, et
     * rendrait tout diagnostic impossible.
     */
    public function testAMissingPageStillReturns404(): void
    {
        $client = static::createClient();
        $this->loggedInUser($client, null);

        $client->request('GET', '/cette-page-n-existe-pas');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnAnonymousVisitorIsNotAffected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }

    /**
     * Le parcours nominal de bout en bout : c'est la seule preuve que le détour
     * a une sortie.
     */
    public function testFillingTheFormReleasesNavigation(): void
    {
        $client = static::createClient();
        $this->loggedInUser($client, null);

        $client->request('GET', '/profil/completer');
        $client->submitForm('Continuer', [
            'profile_completion[firstname]' => 'Camille',
        ]);

        $this->assertResponseRedirects('/dashboard');

        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
    }

    /**
     * Le nom reste facultatif : le formulaire doit accepter d'être soumis sans.
     */
    public function testTheLastnameStaysOptional(): void
    {
        $client = static::createClient();
        $user = $this->loggedInUser($client, null);
        $id = $user->getId();

        $client->request('GET', '/profil/completer');
        $client->submitForm('Continuer', [
            'profile_completion[firstname]' => 'Camille',
        ]);

        $this->assertResponseRedirects('/dashboard');

        // Relire depuis la base : l'objet construit ici n'a pas été touché par
        // la requête HTTP, il ne prouverait rien de ce qui a été enregistré.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(User::class, $id);

        $this->assertSame('Camille', $reloaded->getFirstname());
        $this->assertNull($reloaded->getLastname());
    }

    public function testTheFirstnameIsRefusedWhenLeftEmpty(): void
    {
        $client = static::createClient();
        $this->loggedInUser($client, null);

        $client->request('GET', '/profil/completer');
        $client->submitForm('Continuer', [
            'profile_completion[firstname]' => '',
        ]);

        // 422 et non 200 : depuis Symfony 6.2, un formulaire réaffiché à cause
        // d'une erreur de validation répond « contenu non traitable ». La page
        // est bien rendue — ce n'est pas une panne, c'est un refus.
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains(
            '.login-card',
            'Veuillez renseigner un prénom ou un pseudo.',
        );
    }
}
