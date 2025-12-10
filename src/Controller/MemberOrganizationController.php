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
        // $user = $this->getUser();

        // if (!$user) {
        //     return $this->redirectToRoute('app_login');
        // }

        // $userId = $user->getId();

        // $organizations = $organizationRepository->findByUser($userId);

        return $this->render('member_organization/index.html.twig');
    }
}
