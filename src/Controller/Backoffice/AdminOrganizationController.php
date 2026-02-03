<?php

namespace App\Controller\Backoffice;

use App\Entity\Organization;
use App\Form\OrganizationType;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// This controller is only accessible by admin
#[Route('/admin/organization')]
final class AdminOrganizationController extends AbstractController
{
    #[Route(name: 'app_organization_index', methods: ['GET'])]
    public function index(OrganizationRepository $organizationRepository): Response
    {
        return $this->render('admin/organization/index.html.twig', [
            'organizations' => $organizationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_organization_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

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
            // debut : ajout de l'utilisateur connecter à l'organization
            $user = $security->getUser();
            if ($user) {
                $organization->addUser($user);
            } // fin

            $entityManager->persist($organization);
            $entityManager->flush();

            return $this->redirectToRoute('app_organization_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/organization/new.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organization_show', methods: ['GET'])]
    public function show(Organization $organization): Response
    {
        return $this->render('admin/organization/show.html.twig', [
            'organization' => $organization,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_organization_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Organization $organization, EntityManagerInterface $entityManager, Security $security): Response
    {
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

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

            return $this->redirectToRoute('app_organization_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/organization/edit.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organization_delete', methods: ['POST'])]
    public function delete(Request $request, Organization $organization, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $organization->getId(), $request->getPayload()->getString('_token'))) {

            // Supprimer l'image du disque
            if ($organization->getPicture()) {
            unlink($this->getParameter('organization_logo') . '/' . $organization->getPicture());
            }

            // Supprimer les relations utilisateurs
            foreach ($organization->getUsers() as $user) {
                $organization->removeUser($user);
            }

            // Supprimer l'entité
            $entityManager->remove($organization);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_organization_index', [], Response::HTTP_SEE_OTHER);
    }
}
