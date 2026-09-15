<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * getDisplayName() nomme les personnes dans toute l'interface — quinze
 * gabarits et InvitationService s'appuient dessus. Sa valeur n'est jamais
 * nulle : c'est ce qui permet aux gabarits de l'afficher sans se demander si
 * le profil est rempli, et ce qui a permis de supprimer les quatre blocs
 * conditionnels qui refaisaient ce calcul chacun de leur côté.
 */
final class UserTest extends TestCase
{
    private function user(string $email, ?string $firstname, ?string $lastname = null): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstname($firstname);
        $user->setLastname($lastname);

        return $user;
    }

    public function testAFullProfileIsShownAsFirstnameThenLastname(): void
    {
        $user = $this->user('jean@example.com', 'Jean', 'Dupont');

        $this->assertSame('Jean Dupont', $user->getDisplayName());
    }

    /**
     * Le cas nominal depuis que le nom est facultatif : il ne doit pas laisser
     * traîner l'espace séparateur.
     */
    public function testAFirstnameAloneIsShownWithoutATrailingSpace(): void
    {
        $user = $this->user('jean@example.com', 'Jean');

        $this->assertSame('Jean', $user->getDisplayName());
    }

    /**
     * L'inverse est possible en back-office, où AdminUserCreationType laisse
     * les deux champs libres.
     */
    public function testALastnameAloneIsShownWithoutALeadingSpace(): void
    {
        $user = $this->user('dupont@example.com', null, 'Dupont');

        $this->assertSame('Dupont', $user->getDisplayName());
    }

    /**
     * Les comptes créés depuis le back-office et jamais connectés n'ont pas
     * encore traversé l'écran de complétion : l'e-mail les nomme en attendant.
     */
    public function testAnEmptyProfileFallsBackToTheEmail(): void
    {
        $user = $this->user('sans-nom@example.com', null);

        $this->assertSame('sans-nom@example.com', $user->getDisplayName());
    }

    /**
     * Invariant partagé avec ProfileCompletionSubscriber, qui applique le même
     * trim() pour décider s'il faut rediriger. Si l'un des deux cessait de
     * considérer les espaces comme un profil vide, on obtiendrait soit un nom
     * affiché blanc que plus rien ne signale, soit une redirection sans fin.
     */
    public function testAFirstnameMadeOfSpacesCountsAsEmpty(): void
    {
        $user = $this->user('espaces@example.com', '   ');

        $this->assertSame('espaces@example.com', $user->getDisplayName());
    }

    /**
     * La garantie sur laquelle reposent tous les gabarits : jamais null, donc
     * jamais de cellule vide ni de « Bonjour  ! » sans nom.
     */
    public function testTheDisplayNameIsNeverNull(): void
    {
        $this->assertIsString($this->user('a@example.com', null)->getDisplayName());
        $this->assertIsString($this->user('b@example.com', 'Jean')->getDisplayName());
        $this->assertIsString($this->user('c@example.com', 'Jean', 'Dupont')->getDisplayName());
    }
}
