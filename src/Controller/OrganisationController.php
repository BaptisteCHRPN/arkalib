<?php

namespace App\Controller;

use App\Entity\Organisation;
use App\Form\OrganisationType;
use App\Repository\OrganisationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// This controller is only accessible by admin
#[Route('/organisation')]
final class OrganisationController extends AbstractController
{
    #[Route(name: 'app_organisation_index', methods: ['GET'])]
    public function index(OrganisationRepository $organisationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('organisation/index.html.twig', [
            'organisations' => $organisationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_organisation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $organisation = new Organisation();
        $form = $this->createForm(OrganisationType::class, $organisation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // debut : ajout de l'utilisateur connecter à l'organisation
             $user = $security->getUser();
            if ($user) {
                $organisation->addUser($user);
            } // fin

            $entityManager->persist($organisation);
            $entityManager->flush();

            return $this->redirectToRoute('app_organisation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('organisation/new.html.twig', [
            'organisation' => $organisation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organisation_show', methods: ['GET'])]
    public function show(Organisation $organisation): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('organisation/show.html.twig', [
            'organisation' => $organisation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_organisation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Organisation $organisation, EntityManagerInterface $entityManager, Security $security): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(OrganisationType::class, $organisation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

             // debut : ajout de l'utilisateur connecter à l'organisation
             $user = $security->getUser();
            if ($user) {
                $organisation->addUser($user);
            } // fin

            $entityManager->flush();

            return $this->redirectToRoute('app_organisation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('organisation/edit.html.twig', [
            'organisation' => $organisation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_organisation_delete', methods: ['POST'])]
    public function delete(Request $request, Organisation $organisation, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        if ($this->isCsrfTokenValid('delete'.$organisation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($organisation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_organisation_index', [], Response::HTTP_SEE_OTHER);
    }
}
