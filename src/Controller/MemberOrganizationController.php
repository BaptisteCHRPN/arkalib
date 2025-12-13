<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Form\OrganizationType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\OrganizationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

// This controller is  accessible by connected users
final class MemberOrganizationController extends AbstractController
{
    #[Route('/member/organization', name: 'app_member_organization')]
    public function index(OrganizationRepository $organizationRepository): Response
    {
        $user = $this->getUser(); // Récupère l'utilisateur connecté
        $organizations = $organizationRepository->findOrganizationsByUser($user);

        return $this->render('member_organization/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    #[Route('/new', name: 'app_membre_organization_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {

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

            return $this->redirectToRoute('app_member_organization', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('organization/new.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organization_delete', methods: ['POST'])]
    public function delete(Request $request, Organization $organization, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$organization->getId(), $request->getPayload()->getString('_token'))) {
            foreach ($organization->getUsers() as $user) {
            $organization->removeUser($user);
        }
            dump($organization->getUsers()->count());
            $entityManager->remove($organization);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_organization_index', [], Response::HTTP_SEE_OTHER);
    }
}
