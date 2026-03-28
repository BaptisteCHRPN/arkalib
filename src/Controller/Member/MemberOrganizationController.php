<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\Organization;
use App\Form\OrganizationType;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

// This controller is  accessible by connected users
final class MemberOrganizationController extends AbstractController
{
    // This methode à unset because Dashboard controller index make exactly 
    // #[Route('/member/organization', name: 'app_member_organization')]
    // public function index(OrganizationRepository $organizationRepository): Response
    // {
    //     $user = $this->getUser();
    //     $organizations = $organizationRepository->findOrganizationsByUser($user);

    //     return $this->render('member/dashboard/index.html.twig', [
    //         'organizations' => $organizations,
    //     ]);
    // }
    #[IsGranted('ROLE_USER')]
    #[Route('/organisation/new', name: 'app_membre_organization_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security, SluggerInterface $slugger): Response
    {
        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);
        $user = $this->getUser();

        if ($form->isSubmitted() && $form->isValid()) {

            // auto slug generation
            $slug = $slugger->slug($organization->getName())->lower();
            $organization->setSlug($slug);

            // If picture is submit
            $picture = $form->get('picture')->getData();
            if ($picture) {
                // define file name
                $nameFile = date('YmdHis') . '-' . rand(1000, 9999) . '.' . $picture->getClientOriginalExtension();
                // save file in project
                // organization_logo is defined in service.yaml
                $picture->move($this->getParameter('organization_logo'), $nameFile);
                // save namefile in organization object to inject it in bdd
                $organization->setPicture($nameFile);
            }

            $organization->addUser($user);

            $entityManager->persist($organization);
            $entityManager->flush();
            $this->addFlash('success', 'L\'organisation à été créée avec succès !');

            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/organization/new.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/budgets', name: 'app_organization_budgets')]
    public function showBudgets(
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization
    ): Response {
        $budgets = $organization->getBudgets();
        $users = $organization->getUsers();

        if (!$organization->getUsers()->contains($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('member/dashboard/show_budgets.html.twig', [
            'organization' => $organization,
            'budgets' => $budgets,
            'users' => $users
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/delete-picture', name: 'app_member_organization_delete_picture', methods: ['GET'])]
    public function deletePicture(
        Request $request,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        EntityManagerInterface $entityManager
    ): Response {
        if ($organization->getPicture()) {
            $filePath = $this->getParameter('organization_logo') . '/' . $organization->getPicture();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $organization->setPicture(null);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_member_organization_edit', ['organizationSlug' => $organization->getSlug()], Response::HTTP_SEE_OTHER);
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/{organizationSlug}/edit', name: 'app_member_organization_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])]
        Organization $organization,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);
        $budgets = $organization->getBudgets();

        if ($form->isSubmitted() && $form->isValid()) {

            $picture = $form->get('picture')->getData();
            if ($picture) {
                $nameFile = date('YmdHis') . '-' . rand(1000, 9999) . '.' . $picture->getClientOriginalExtension();
                $picture->move(
                    $this->getParameter('organization_logo'),
                    $nameFile
                );

                if ($organization->getPicture()) {
                    unlink($this->getParameter('organization_logo') . '/' . $organization->getPicture());
                }
                $organization->setPicture($nameFile);
            }

            // link connecteed user to organization
            $user = $security->getUser();
            if ($user) {
                $organization->addUser($user);
            }

            $entityManager->flush();
            $this->addFlash('success', 'L\'organisation à été modifiée avec succès !');

            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/organization/edit.html.twig', [
            'organization' => $organization,
            'form' => $form,
            'budgets' => $budgets
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/organization/{id}', name: 'app_member_organization_delete', methods: ['POST'])]
    public function delete(Request $request, Organization $organization, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $organization->getId(), $request->getPayload()->getString('_token'))) {

            if ($organization->getPicture()) {
                unlink($this->getParameter('organization_logo') . '/' . $organization->getPicture());
            }

            foreach ($organization->getUsers() as $user) {
                $organization->removeUser($user);
            }

            $entityManager->remove($organization);
            $entityManager->flush();
            $this->addFlash('success', 'L\'organisation à été supprimée avec succès !');
        }

        return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
    }
}
