<?php

namespace App\Controller\Backoffice;

use App\Entity\Invitation;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\OrganizationMembershipRepository;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/{id}', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {

            if($user->getPicture()){
                unlink($this->getParameter('user_avatar') . '/' );
            }

            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
