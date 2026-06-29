<?php

namespace App\Controller\Public\Security;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\ResendVerificationEmailFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(private EmailVerifier $emailVerifier) {}

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('verify@arkalib.fr', 'Arkalib'))
                    ->to((string) $user->getEmail())
                    ->subject('Veuillez confirmation votre email')
                    ->htmlTemplate('public/registration/confirmation_email.html.twig')
            );

            $request->getSession()->set('registration_pending_email', $user->getEmail());

            return $this->redirectToRoute('app_registration_pending');
        }

        return $this->render('public/registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/register/pending', name: 'app_registration_pending')]
    public function registrationPending(Request $request): Response
    {
        $email = $request->getSession()->get('registration_pending_email');

        if (!$email) {
            return $this->redirectToRoute('app_register');
        }

        $request->getSession()->remove('registration_pending_email');

        return $this->render('public/registration/registration_pending.html.twig', [
            'email' => $email,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository, InvitationService $invitationService): Response
    {


        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            /** @var User $user */
            $userId = $request->query->get('id');
            $user = $userRepository->find($userId);

            if (!$user) {
                return $this->redirectToRoute('app_register');
            }

            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Votre adresse email a été vérifiée. Connectez-vous pour accéder à votre espace.');

        // Cas 3 : invitation en attente pour un nouvel inscrit
        // On traite l'invitation ici, au moment où l'identité est confirmée (email vérifié),
        // plutôt que de dépendre d'une session fragile qui pourrait ne pas survivre
        // jusqu'au LoginSuccessEvent.
        $session = $request->getSession();
        $pendingToken = $session->get('pending_invitation_token');
        if ($pendingToken) {
            $session->remove('pending_invitation_token');
            try {
                $invitationService->accept($pendingToken, $user);
                $this->addFlash('success', 'Vous avez été ajouté à votre organisation. Connectez-vous pour y accéder.');
            } catch (\LogicException) {
                $this->addFlash('info', 'L\'invitation avait expiré. Demandez un nouvel envoi à votre contact.');
            }
        }

        // Clean up the target path to prevent unwanted redirections
        $this->removeTargetPath($request->getSession(), 'main');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/resend/verify/email', name: 'app_verify_resend')]
    public function resendEmailConfirmation(Request $request, UserRepository $userRepository): Response
    {
        $form = $this->createForm(ResendVerificationEmailFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user && !$user->isVerified()) {
                $this->emailVerifier->sendEmailConfirmation(
                    'app_verify_email',
                    $user,
                    (new TemplatedEmail())
                        ->from(new Address('verify@arkalib.fr', 'Arkalib'))
                        ->to((string) $user->getEmail())
                        ->subject('Veuillez vérifier votre email')
                        ->htmlTemplate('public/registration/confirmation_email.html.twig')
                );
            }
            $this->addFlash('success', 'L\'email de vérification à été envoyé avec succès. Veuillez vérifier votre messagerie.');

            return $this->redirectToRoute('app_verify_resend');
        }

        return $this->render('public/registration/resend_email.html.twig', [
            'form' => $form,
        ]);
    }
}
