<?php

namespace App\Controller;

use App\Repository\OrganizationRepository;
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
}
