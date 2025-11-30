<?php

namespace App\Controller\Admin;

use App\Repository\OrganisationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(OrganisationRepository $organisationRepository, 
                          UserRepository $userRepository): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
            'organisations' => $organisationRepository->findAll(),
            'users' => $userRepository->findAll(),
        ]);
    }
}
