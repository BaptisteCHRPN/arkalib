<?php

namespace App\Controller;

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
#[Route('/organization')]
final class OrganizationController extends AbstractController
{
    #[Route(name: 'app_organization_index', methods: ['GET'])]
    public function index(OrganizationRepository $organizationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('organization/index.html.twig', [
            'organizations' => $organizationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_organization_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // debut : ajout de l'utilisateur connecter à l'organization
             $user = $security->getUser();
            if ($user) {
                $organization->addUser($user);
            } // fin

            $entityManager->persist($organization);
            $entityManager->flush();

            return $this->redirectToRoute('app_organization_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('organization/new.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organization_show', methods: ['GET'])]
    public function show(Organization $organization): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('organization/show.html.twig', [
            'organization' => $organization,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_organization_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Organization $organization, EntityManagerInterface $entityManager, Security $security): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

             // debut : ajout de l'utilisateur connecter à l'organization
             $user = $security->getUser();
            if ($user) {
                $organization->addUser($user);
            } // fin

            $entityManager->flush();

            return $this->redirectToRoute('app_organization_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('organization/edit.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organization_delete', methods: ['POST'])]
    public function delete(Request $request, Organization $organization, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$organization->getId(), $request->getPayload()->getString('_token'))) {
            foreach ($organization->getUsers() as $user) {
            $organization->removeUser($user);
        }
            // dd("Nombre d'utilisateurs après suppression : " . $organization->getUsers()->count());
            $entityManager->remove($organization);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_organization_index', [], Response::HTTP_SEE_OTHER);
    }
}
