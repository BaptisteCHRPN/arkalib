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
 * Envoie un lien permettant de choisir un mot de passe.
 *
 * Deux usages, un même mécanisme de jeton mais deux messages : on ne dit pas
 * la même chose à quelqu'un qui a oublié son mot de passe qu'à quelqu'un dont
 * on vient de créer le compte — à ce dernier, « ignorez cet e-mail » rendrait
 * le compte inutilisable.
 *
 * La logique vivait dans une méthode privée de ResetPasswordController, donc
 * inatteignable depuis le back-office. Autre différence assumée entre les deux
 * appelants : le formulaire public tait les échecs pour ne pas révéler
 * l'existence d'un compte, alors que l'exploitant a besoin de savoir pourquoi
 * son envoi n'est pas parti.
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
    public function sendResetTo(User $user): ResetPasswordToken
    {
        return $this->send(
            $user,
            'Réinitialisation de votre mot de passe',
            'public/reset_password/email.html.twig',
        );
    }

    /**
     * @throws ResetPasswordExceptionInterface
     */
    public function sendAccountInitializationTo(User $user): ResetPasswordToken
    {
        return $this->send(
            $user,
            'Votre compte Arkalib a été créé',
            'admin/user/account_created_email.html.twig',
        );
    }

    private function send(User $user, string $subject, string $template): ResetPasswordToken
    {
        $resetToken = $this->resetPasswordHelper->generateResetToken($user);

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to((string) $user->getEmail())
                ->subject($subject)
                ->htmlTemplate($template)
                ->context(['resetToken' => $resetToken, 'user' => $user])
        );

        return $resetToken;
    }
}
