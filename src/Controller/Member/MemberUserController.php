<?php

namespace App\Controller\Member;

use App\Entity\User;
use App\Form\ChangeEmailFormType;
use App\Form\UserType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/user')]
final class MemberUserController extends AbstractController
{
    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {

        return $this->render('member/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_member_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $picture = $form->get('picture')->getData();
            if ($picture) {
                $userFirstName = $user->getFirstName() ? preg_replace('/[^a-z0-9]/i', '', strtolower($user->getFirstName())) : 'user';
                $nameFile = date('YmdHis') . '-' . $userFirstName . '-' . rand(1000, 9999) . '.' . $picture->getClientOriginalExtension();
                $picture->move($this->getParameter('user_avatar'), $nameFile);

                if ($user->getPicture()) {
                    unlink($this->getParameter('user_avatar') . '/' . $user->getPicture());
                }
                $user->setPicture($nameFile);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
            'formChangeEmail' => $this->createForm(ChangeEmailFormType::class),
        ]);
    }

    #[Route('/{id}/change-email', name: 'app_member_user_change_email', methods: ['GET', 'POST'])]
    public function changeEmail(
        Request $request,
        User $user,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        #[Autowire(param: 'mailer_from_address')] string $mailerFromAddress,
        #[Autowire(param: 'mailer_from_name')] string $mailerFromName,
    ): Response {

        if ($this->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ChangeEmailFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEmail = $form->get('newEmail')->getData();
            $token = bin2hex(random_bytes(32));
            $expiresAt = new DateTimeImmutable('+1 hour');

            $user->setPendingEmail($newEmail);
            $user->setEmailChangeToken($token);
            $user->setEmailChangeTokenExpiresAt($expiresAt);

            $entityManager->flush();

            $confirmUrl = $this->generateUrl(
                'app_member_user_confirm_email',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($mailerFromAddress, $mailerFromName))
                ->to($newEmail)
                ->subject('Confirmez votre nouvelle adresse email')
                ->htmlTemplate('member/user/change_email.html.twig')
                ->context([
                    'user' => $user,
                    'confirmUrl' => $confirmUrl,
                ]);

            $mailer->send($email);

            $this->addFlash('success', 'Un email de confirmation a été envoyé à votre nouvelle adresse.');

            return $this->redirectToRoute('app_member_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('member/user/change_email.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }


    #[Route('/{id}/delete-picture', name: 'app_member_user_delete_picture', methods: ['GET'])]
    public function deletePicture(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($user->getPicture()) {
            $filePath = $this->getParameter('user_avatar') . '/' . $user->getPicture();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $user->setPicture(null);
            $entityManager->flush();
        }


        return $this->redirectToRoute('app_member_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }



    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->getPayload()->getString('_token'))) {

            if ($user->getPicture()) {
                unlink($this->getParameter('user_avatar') . '/');
            }

            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
