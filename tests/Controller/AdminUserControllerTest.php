<?php

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminUserControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createUser(string $email, ?string $firstname = null, ?string $lastname = null, array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('not-checked-by-loginUser');
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function loginAsAdmin(): User
    {
        $admin = $this->createUser('admin@example.com', 'Super', 'Admin', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);

        return $admin;
    }

    public function testTheListIsClosedToNonAdmins(): void
    {
        $this->client->loginUser($this->createUser('simple@example.com'));

        $this->client->request('GET', '/admin/user');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheListShowsEveryAccount(): void
    {
        $this->loginAsAdmin();
        $this->createUser('marie.dupont@example.com', 'Marie', 'Dupont');
        $this->createUser('paul.martin@example.com', 'Paul', 'Martin');

        $crawler = $this->client->request('GET', '/admin/user');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Utilisateurs');
        $this->assertCount(3, $crawler->filter('#userTable tbody tr'));
    }

    public function testSearchNarrowsTheListDownToOneAccount(): void
    {
        $this->loginAsAdmin();
        $this->createUser('marie.dupont@example.com', 'Marie', 'Dupont');
        $this->createUser('paul.martin@example.com', 'Paul', 'Martin');

        $crawler = $this->client->request('GET', '/admin/user?q=Marie+Dupont');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#userTable tbody tr'));
        $this->assertSelectorTextContains('#userTable', 'marie.dupont@example.com');
    }

    /**
     * Le terme cherché doit rester dans le champ, sinon on ne sait plus ce
     * qu'on vient de taper en lisant les résultats.
     */
    public function testTheSearchTermStaysInTheField(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/user?q=Dupont');

        $this->assertSame('Dupont', $crawler->filter('#searchInput')->attr('value'));
    }

    public function testAFruitlessSearchExplainsItselfAndOffersAWayBack(): void
    {
        $this->loginAsAdmin();
        $this->createUser('marie.dupont@example.com', 'Marie', 'Dupont');

        $this->client->request('GET', '/admin/user?q=introuvable');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.empty-state', 'introuvable');
        $this->assertSelectorExists('.empty-state a[href="/admin/user"]');
    }

    private function createOrganization(string $name, string $slug): Organization
    {
        $organization = new Organization();
        $organization->setName($name);
        $organization->setSlug($slug);
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        return $organization;
    }

    public function testTheFicheIsClosedToNonAdmins(): void
    {
        $target = $this->createUser('cible@example.com');
        $this->client->loginUser($this->createUser('simple@example.com'));

        $this->client->request('GET', '/admin/user/' . $target->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testTheFicheListsTheOrganizationsTheAccountBelongsTo(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('marie@example.com', 'Marie', 'Dupont');

        $assoX = $this->createOrganization('Asso X', 'asso-x');
        $clubY = $this->createOrganization('Club Y', 'club-y');
        $assoX->addUser($user, OrganizationRole::ADMIN);
        $clubY->addUser($user, OrganizationRole::TREASURER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Asso X');
        $this->assertSelectorTextContains('body', 'Club Y');
        $this->assertSelectorTextContains('body', 'Administrateur');
        $this->assertSelectorTextContains('body', 'Trésorier');
    }

    /**
     * Le chemin qui n'existait pas : de la réclamation vers les données du
     * client, dans l'interface membre.
     */
    public function testEachOrganizationLinksIntoTheMemberInterface(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $organization = $this->createOrganization('Asso X', 'asso-x');
        $organization->addUser($user, OrganizationRole::READER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorExists('a[href="/asso-x/budgets"]');
    }

    public function testAnOrganizationLeftWithoutAnAdminIsFlagged(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $organization = $this->createOrganization('Orpheline', 'orpheline');
        $organization->addUser($user, OrganizationRole::TREASURER);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextContains('body', 'Plus aucun administrateur');
    }

    public function testAnOrganizationWithAnAdminIsNotFlagged(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('marie@example.com', 'Marie', 'Dupont');
        $organization = $this->createOrganization('Saine', 'saine');
        $organization->addUser($user, OrganizationRole::ADMIN);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextNotContains('body', 'Plus aucun administrateur');
    }

    public function testAPendingEmailChangeIsVisible(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('ancienne@example.com', 'Marie', 'Dupont');
        $user->setPendingEmail('nouvelle@example.com');
        $user->setEmailChangeTokenExpiresAt(new \DateTimeImmutable('+2 hours'));
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextContains('body', 'nouvelle@example.com');
        $this->assertSelectorTextContains('body', 'Lien valable jusqu');
    }

    public function testAnUnverifiedAccountSaysWhyItCannotLogIn(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('jamais@example.com');

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextContains('body', 'Non vérifié');
        $this->assertSelectorTextContains('body', 'Inscription jamais confirmée');
    }

    public function testAnAccountWithoutHistoryShowsDashesRatherThanBlanks(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('vierge@example.com');

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', "Ce compte n'appartient à aucune organisation");
        $this->assertSelectorTextContains('body', 'Aucune invitation envoyée');
        $this->assertSelectorTextContains('body', 'Aucune demande enregistrée');
    }

    /**
     * Soumet le vrai formulaire de la fiche plutôt qu'un POST fabriqué : le
     * bouton, son jeton CSRF et sa route sont ainsi testés ensemble.
     */
    private function submitActionFromFiche(User $user, string $routeSuffix): void
    {
        $action = '/admin/user/' . $user->getId() . '/' . $routeSuffix;
        $crawler = $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->client->submit($crawler->filter('form[action="' . $action . '"] button')->form());
    }

    /**
     * Le noyau redémarre entre deux requêtes du client : l'objet d'origine est
     * détaché, il faut le relire depuis un EntityManager frais.
     */
    private function reload(int $id): User
    {
        return static::getContainer()->get(EntityManagerInterface::class)->find(User::class, $id);
    }

    public function testResendingTheVerificationEmailActuallySendsIt(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('pasverifie@example.com');

        $this->submitActionFromFiche($user, 'renvoyer-verification');

        $this->assertResponseRedirects('/admin/user/' . $user->getId());
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'To', 'pasverifie@example.com');
    }

    public function testAnAlreadyVerifiedAccountIsNotOfferedTheResendButton(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('verifie@example.com');
        $user->setIsVerified(true);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorNotExists('form[action$="/renvoyer-verification"]');
    }

    public function testTheActionsRefuseAForgedRequest(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('cible@example.com');

        $this->client->request('POST', '/admin/user/' . $user->getId() . '/renvoyer-verification', ['_token' => 'faux']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertEmailCount(0);
    }

    public function testSendingAPasswordResetLinkActuallySendsIt(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('oubli@example.com');

        $this->submitActionFromFiche($user, 'reinitialiser-mot-de-passe');

        $this->assertResponseRedirects('/admin/user/' . $user->getId());
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'To', 'oubli@example.com');
    }

    public function testGrantingAndRevokingTheAdminRole(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('promu@example.com');

        $id = $user->getId();

        $this->submitActionFromFiche($user, 'role-admin');
        $this->assertContains('ROLE_ADMIN', $this->reload($id)->getRoles());

        $this->submitActionFromFiche($user, 'role-admin');
        $this->assertNotContains('ROLE_ADMIN', $this->reload($id)->getRoles());
    }

    /**
     * Se retirer soi-même le rôle reviendrait à se verrouiller dehors : la
     * fiche de l'opérateur ne propose donc pas le bouton.
     */
    public function testAnAdminIsNotOfferedTheRoleButtonOnTheirOwnFiche(): void
    {
        $admin = $this->loginAsAdmin();

        $this->client->request('GET', '/admin/user/' . $admin->getId());

        $this->assertSelectorNotExists('form[action$="/role-admin"]');
        $this->assertSelectorNotExists('form[action$="/anonymiser"]');
    }

    public function testAnonymizingAnAccountRedirectsToTheList(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('apartir@example.com', 'Marie', 'Dupont');

        $id = $user->getId();

        $this->submitActionFromFiche($user, 'anonymiser');

        $this->assertResponseRedirects('/admin/user');

        $anonymized = $this->reload($id);
        $this->assertNull($anonymized->getFirstname());
        $this->assertStringStartsWith('supprime-', (string) $anonymized->getEmail());
    }

    public function testAnAnonymizedFicheSaysSoAndOffersNoAction(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('apartir@example.com', 'Marie', 'Dupont');
        $this->submitActionFromFiche($user, 'anonymiser');

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextContains('body', 'Ce compte a été anonymisé');
        $this->assertSelectorNotExists('form[action$="/anonymiser"]');
    }

    public function testTheFicheExplainsWhyAnonymizationIsBlocked(): void
    {
        $this->loginAsAdmin();
        $user = $this->createUser('seul@example.com', 'Marie', 'Dupont');
        $organization = $this->createOrganization('Orpheline', 'orpheline');
        $organization->addUser($user, OrganizationRole::ADMIN);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/user/' . $user->getId());

        $this->assertSelectorTextContains('body', 'seul administrateur de « Orpheline »');
        $this->assertSelectorExists('button[disabled]');
    }
}
