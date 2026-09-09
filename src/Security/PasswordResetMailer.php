<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Envoie un lien de réinitialisation de mot de passe.
 *
 * La logique vivait dans une méthode privée de ResetPasswordController, donc
 * inatteignable depuis le back-office. Une différence assumée entre les deux
 * appelants : le formulaire public tait les échecs pour ne pas révéler
 * l'existence d'un compte, alors que l'exploitant, lui, a besoin de savoir
 * pourquoi son envoi n'est pas parti.
 */
class PasswordResetMailer
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        #[Autowire(param: 'mailer_from_address')] private string $mailerFromAddress,
        #[Autowire(param: 'mailer_from_name')] private string $mailerFromName,
    ) {}

    /**
     * @throws ResetPasswordExceptionInterface si un lien a déjà été demandé
     *                                         trop récemment (limitation de débit)
     */
    public function sendTo(User $user): ResetPasswordToken
    {
        $resetToken = $this->resetPasswordHelper->generateResetToken($user);

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to((string) $user->getEmail())
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('public/reset_password/email.html.twig')
                ->context(['resetToken' => $resetToken])
        );

        return $resetToken;
    }
}
