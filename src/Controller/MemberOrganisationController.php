<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// This controller is  accessible by connected users
final class MemberOrganisationController extends AbstractController
{
    #[Route('/member/organisation', name: 'app_member_organisation')]
    public function index(): Response
    {
        return $this->render('member_organisation/index.html.twig', [
            'controller_name' => 'MemberOrganisationController',
        ]);
    }
}
