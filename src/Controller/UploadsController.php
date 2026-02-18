<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UploadsController extends AbstractController
{
    #[Route('/uploads/organization_logo/{nameFile}', name: 'organization_logo_upload')]
    public function organizationLogo(string $nameFile): Response
    {
        $pathFile = $this->getParameter('organization_logo') . '/' . $nameFile;

        if(!file_exists($pathFile)) {
            throw $this->createNotFoundException('Logo non trouvé');
        }
        return new BinaryFileResponse($pathFile);
    }

    #[Route('/uploads/user_avatar/{nameFile}', name: 'user_avatar_upload')]
    public function userAvatar(string $nameFile): Response
    {
        $pathFile = $this->getParameter('user_avatar') . '/' . $nameFile;

        if(!file_exists($pathFile)) {
            throw $this->createNotFoundException('Avatar non trouvé');
        }
        return new BinaryFileResponse($pathFile);
    }

    #[Route('/uploads/budget_file/{nameFile}', name: 'budget_file_upload')]
    public function budgetFile(string $nameFile): Response
    {
        $pathFile = $this->getParameter('budget_file') . '/' . $nameFile;

        if (!file_exists($pathFile)) {
            throw $this->createNotFoundException('Fichié non trouvé');
        }
        return new BinaryFileResponse($pathFile);
    }

    #[Route('/uploads/transaction_file/{nameFile}', name: 'transaction_file_upload')]
    public function transactionFile(string $nameFile): Response
    {
        $pathFile = $this->getParameter('transaction_file') . '/' . $nameFile;

        if (!file_exists($pathFile)) {
            throw $this->createNotFoundException('Fichié non trouvé');
        }
        return new BinaryFileResponse($pathFile);
    }

}
