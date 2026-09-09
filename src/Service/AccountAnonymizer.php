<?php

namespace App\Service;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Repository\OrganizationMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Efface les données personnelles d'un compte sans supprimer sa ligne.
 *
 * Supprimer le compte est impossible et ne serait pas souhaitable : dix-huit
 * clés étrangères pointent vers `user` sans ON DELETE, et les faire tomber
 * effacerait l'auteur des écritures comptables de ses collègues. Le droit à
 * l'effacement porte sur les données personnelles, pas sur le lien technique
 * qui dit qui a saisi quoi.
 */
class AccountAnonymizer
{
    public const EMAIL_PREFIX = 'supprime-';
    public const EMAIL_DOMAIN = '@arkalib.local';

    public function __construct(
        private EntityManagerInterface $em,
        private OrganizationMembershipRepository $membershipRepository,
        private Filesystem $filesystem,
        #[Autowire(param: 'user_avatar')] private string $avatarDirectory,
    ) {}

    /**
     * Les organisations dont ce compte est le seul administrateur.
     *
     * @return string[] noms d'organisations, vide si l'anonymisation peut se faire
     */
    public function blockingOrganizations(User $user): array
    {
        $blocking = [];

        foreach ($user->getMemberships() as $membership) {
            if (!$membership->isAdmin()) {
                continue;
            }

            if ($this->membershipRepository->countAdmins($membership->getOrganization()) <= 1) {
                $blocking[] = (string) $membership->getOrganization()->getName();
            }
        }

        return $blocking;
    }

    public function isAnonymized(User $user): bool
    {
        return str_starts_with((string) $user->getEmail(), self::EMAIL_PREFIX);
    }

    /**
     * @throws \LogicException si le compte est le dernier administrateur d'une
     *                         organisation, qui se retrouverait sans pilote
     */
    public function anonymize(User $user): void
    {
        $blocking = $this->blockingOrganizations($user);

        if ([] !== $blocking) {
            throw new \LogicException(sprintf(
                'Ce compte est le seul administrateur de %s. Désignez un autre administrateur avant de l\'anonymiser.',
                implode(', ', array_map(static fn (string $name) => '« ' . $name . ' »', $blocking)),
            ));
        }

        $this->deleteAvatar($user);
        $this->deleteResetPasswordRequests($user);

        foreach ($user->getMemberships()->toArray() as $membership) {
            $membership->getOrganization()?->removeMembership($membership);
            $user->removeMembership($membership);
            $this->em->remove($membership);
        }

        $user->setEmail(self::EMAIL_PREFIX . $user->getId() . self::EMAIL_DOMAIN);
        $user->setFirstname(null);
        $user->setLastname(null);
        $user->setPicture(null);
        $user->setProfilePicture(null);
        $user->setPendingEmail(null);
        $user->setEmailChangeToken(null);
        $user->setEmailChangeTokenExpiresAt(null);
        $user->setLastLoginAt(null);
        $user->setIsVerified(false);
        $user->setRoles([]);

        // Aucun mot de passe ne peut produire cette empreinte : le compte
        // devient inaccessible sans qu'il reste trace de l'ancien.
        $user->setPassword(bin2hex(random_bytes(32)));

        $this->em->flush();
    }

    private function deleteAvatar(User $user): void
    {
        if (null === $user->getPicture()) {
            return;
        }

        $this->filesystem->remove($this->avatarDirectory . '/' . $user->getPicture());
    }

    private function deleteResetPasswordRequests(User $user): void
    {
        $requests = $this->em->getRepository(ResetPasswordRequest::class)->findBy(['user' => $user]);

        foreach ($requests as $request) {
            $this->em->remove($request);
        }
    }
}
