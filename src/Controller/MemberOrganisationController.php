<?php

namespace App\Controller;

use App\Repository\OrganisationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

// This controller is  accessible by connected users
final class MemberOrganisationController extends AbstractController
{
    #[Route('/member/organisation', name: 'app_member_organisation')]
    public function index(OrganisationRepository $organisationRepository): Response
    {        
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $user->getId();

        $organisations = $organisationRepository->findByUser($userId);

        return $this->render('member_organisation/index.html.twig', [
            'organisations' => $organisations,
        ]);
    }
}
