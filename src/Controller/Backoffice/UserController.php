<?php

namespace App\Controller\Backoffice;

use App\Entity\Invitation;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\OrganizationMembershipRepository;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Security\PasswordResetMailer;
use App\Service\AccountAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

#[Route('admin/user')]
final class UserController extends AbstractController
{
    #[Route(name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $search = $request->query->getString('q');

        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepository->search($search),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $picture = $form->get('picture')->getData();
            if($picture) {
                $userFirstName = $user->getFirstName() ? preg_replace('/[^a-z0-9]/i', '', strtolower($user->getFirstName())) : 'user';
                $nameFile = date('YmdHis') . '-' . $userFirstName . '-' . rand(1000, 9999) . '.' . $picture->getClientOriginalExtension();
                $picture->move($this->getParameter('avatar_user'), $nameFile);
                $user->setPicture($nameFile);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_show', methods: ['GET'])]
    public function show(
        User $user,
        OrganizationMembershipRepository $membershipRepository,
        ResetPasswordRequestRepository $resetPasswordRequestRepository,
        AccountAnonymizer $anonymizer,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Le nombre d'administrateurs est compté ici plutôt que dans le
        // template : une organisation qui n'en a plus est un état cassé qu'il
        // faut voir depuis la fiche de n'importe lequel de ses membres.
        $organizations = [];
        foreach ($user->getMemberships() as $membership) {
            $organization = $membership->getOrganization();

            $organizations[] = [
                'membership' => $membership,
                'organization' => $organization,
                'adminCount' => $membershipRepository->countAdmins($organization),
            ];
        }

        usort(
            $organizations,
            static fn (array $a, array $b) => strcasecmp(
                (string) $a['organization']->getName(),
                (string) $b['organization']->getName(),
            ),
        );

        $invitations = $user->getInvitations()->toArray();
        usort(
            $invitations,
            static fn (Invitation $a, Invitation $b) => $b->getCreatedAt() <=> $a->getCreatedAt(),
        );

        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
            'organizations' => $organizations,
            'invitations' => $invitations,
            'resetRequest' => $resetPasswordRequestRepository->findMostRecentForUser($user),
            'anonymizationBlockers' => $anonymizer->blockingOrganizations($user),
            'isAnonymized' => $anonymizer->isAnonymized($user),
        ]);
    }

    #[Route('/admin/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $picture = $form->get('picture')->getData();
            if($picture) {
                $userFirstName = $user->getFirstName() ? preg_replace('/[^a-z0-9]/i', '', strtolower($user->getFirstName())) : 'user';
                $nameFile = date('YmdHis') . '-' . $userFirstName . '-' . rand(1000, 9999) . '.' . $picture->getClientOriginalExtension();
                $picture->move($this->getParameter('user_avatar'), $nameFile);

                if($user->getPicture()) {
                    unlink($this->getParameter('user_avatar') . '/' . $user->getPicture());
                }
                $user->setPicture($nameFile);
            }
        
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/renvoyer-verification', name: 'app_admin_user_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request, User $user, EmailVerifier $emailVerifier): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('resend_verification' . $user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($user->isVerified()) {
            $this->addFlash('warning', 'Ce compte est déjà vérifié, aucun email n\'a été envoyé.');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $emailVerifier->sendVerificationEmailTo($user);
        $this->addFlash('success', sprintf('Email de vérification renvoyé à %s.', $user->getEmail()));

        return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reinitialiser-mot-de-passe', name: 'app_admin_user_send_password_reset', methods: ['POST'])]
    public function sendPasswordReset(Request $request, User $user, PasswordResetMailer $passwordResetMailer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('password_reset' . $user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $passwordResetMailer->sendTo($user);
            $this->addFlash('success', sprintf('Lien de réinitialisation envoyé à %s.', $user->getEmail()));
        } catch (ResetPasswordExceptionInterface $exception) {
            // Contrairement au formulaire public, on dit ici pourquoi l'envoi
            // n'est pas parti : sans ça l'exploitant croit avoir agi.
            $this->addFlash('error', 'Aucun lien envoyé : une demande trop récente est encore en cours pour ce compte.');
        }

        return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/role-admin', name: 'app_admin_user_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('toggle_admin' . $user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier votre propre rôle d\'administrateur.');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            $user->setRoles(array_values(array_diff($roles, ['ROLE_ADMIN', 'ROLE_USER'])));
            $this->addFlash('success', 'Le rôle administrateur a été retiré.');
        } else {
            $user->setRoles(array_values(array_unique([...$roles, 'ROLE_ADMIN'])));
            $this->addFlash('success', 'Le rôle administrateur a été accordé.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/anonymiser', name: 'app_admin_user_anonymize', methods: ['POST'])]
    public function anonymize(Request $request, User $user, AccountAnonymizer $anonymizer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('anonymize' . $user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas anonymiser votre propre compte.');

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        try {
            $anonymizer->anonymize($user);
            $this->addFlash('success', 'Le compte a été anonymisé. Les écritures qu\'il a créées gardent leur auteur.');
        } catch (\LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
