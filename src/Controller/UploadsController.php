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
}
