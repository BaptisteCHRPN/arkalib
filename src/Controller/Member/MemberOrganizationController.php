<?php

namespace App\Controller\Member;

use App\Entity\Organization;
use App\Form\OrganizationType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\OrganizationRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

            $organization->addUser($user);
        
            $entityManager->persist($organization);
            $entityManager->flush();

            return $this->redirectToRoute('app_dashboard', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/organization/new.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }
    #[Route('/organization/{slug}/budgets', name: 'app_organization_budgets')]
    public function showBudgets(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Organization $organization
    ): Response
    {
        $budgets = $organization->getBudgets();

        if(!$organization->getUsers()->contains($this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('member/dashboard/show_budgets.html.twig', [
            'organization' => $organization,
            'budgets' => $budgets,
        ]);
    }

    #[Route('/organization/{id}', name: 'app_organization_delete', methods: ['POST'])]
    public function delete(Request $request, Organization $organization, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $organization->getId(), $request->getPayload()->getString('_token'))) {
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
