<?php

namespace App\Controller;

use App\Repository\BudgetLineRepository;
use App\Repository\TransactionRepository;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UploadsController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/uploads/organization_logo/{nameFile}', name: 'organization_logo_upload')]
    public function organizationLogo(string $nameFile): Response
    {
        $pathFile = $this->getParameter('organization_logo') . '/' . $nameFile;

        if(!file_exists($pathFile)) {
            throw $this->createNotFoundException('Logo non trouvé');
        }
        return new BinaryFileResponse($pathFile);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/uploads/user_avatar/{nameFile}', name: 'user_avatar_upload')]
    public function userAvatar(string $nameFile): Response
    {
        $pathFile = $this->getParameter('user_avatar') . '/' . $nameFile;

        if(!file_exists($pathFile)) {
            throw $this->createNotFoundException('Avatar non trouvé');
        }
        return new BinaryFileResponse($pathFile);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/uploads/budget_file/{nameFile}', name: 'budget_file_upload')]
    public function budgetFile(string $nameFile, BudgetLineRepository $budgetLineRepository): Response
    {
        $pathFile = $this->getParameter('budget_file') . '/' . $nameFile;

        if (!file_exists($pathFile)) {
            throw $this->createNotFoundException('Fichié non trouvé');
        }

        $budgetLine = $budgetLineRepository->findOneBy(['attachment' => $nameFile]);

        if (!$budgetLine || !$budgetLine->getBudget()) {
            throw $this->createNotFoundException('Fichié non trouvé');
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $budgetLine->getBudget()->getOrganization());

        return new BinaryFileResponse($pathFile);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/uploads/transaction_file/{nameFile}', name: 'transaction_file_upload')]
    public function transactionFile(string $nameFile, TransactionRepository $transactionRepository): Response
    {
        $pathFile = $this->getParameter('transaction_file') . '/' . $nameFile;

        if (!file_exists($pathFile)) {
            throw $this->createNotFoundException('Fichié non trouvé');
        }

        $transaction = $transactionRepository->findOneBy(['attachment' => $nameFile]);

        if (!$transaction || $transaction->getBudgetLine()->isEmpty()) {
            throw $this->createNotFoundException('Fichié non trouvé');
        }

        $organization = $transaction->getBudgetLine()->first()->getBudget()?->getOrganization();

        if (!$organization) {
            throw $this->createNotFoundException('Fichié non trouvé');
        }

        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);

        return new BinaryFileResponse($pathFile);
    }

}
