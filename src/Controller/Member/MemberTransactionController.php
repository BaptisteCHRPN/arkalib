<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Form\TransactionType;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MemberTransactionController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/transaction', name: 'app_member_transaction_index', methods: ['GET'])]
    public function index(
        TransactionRepository $transactionRepository,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
    ): Response {
        return $this->render('member/transaction/index.html.twig', [
            'transactions' => $transactionRepository->findByBudget($budget),
            'organization' => $organization,
            'budget' => $budget,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/transaction/new', name: 'app_member_transaction_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
    ): Response {
        $transaction = new Transaction();
        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $attachment = $form->get('attachment')->getData();
            $transactionName = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $transaction->getReference()), '-'));

            if ($attachment) {
                $nameFile = $transactionName . '-' . date('YmdHis') . '.' . $attachment->getClientOriginalExtension();
                $attachment->move($this->getParameter('transaction_file'), $nameFile);

                if ($transaction->getAttachment()) {
                    unlink($this->getParameter('transaction_file') . '/' . $transaction->getAttachment());
                }

                $transaction->setAttachment($nameFile);
            }

            $entityManager->persist($transaction);
            $entityManager->flush();
            $this->addFlash('success', 'La transaction à été créée avec succès !');

            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/transaction/new.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
            'organization' => $organization,
            'budget' => $budget,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/transaction/{id}', name: 'app_member_transaction_show', methods: ['GET'])]
    public function show(
        Transaction $transaction,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
    ): Response {
        return $this->render('member/transaction/show.html.twig', [
            'transaction' => $transaction,
            'organization' => $organization,
            'budget' => $budget,
        ]);
    }

#[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/transaction/{id}/edit', name: 'app_member_transaction_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Transaction $transaction,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
    ): Response {
        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $attachment = $form->get('attachment')->getData();
            $transactionName = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $transaction->getReference()), '-'));

            if ($attachment) {
                $nameFile = $transactionName . '-' . date('YmdHis') . '.' . $attachment->getClientOriginalExtension();
                $attachment->move($this->getParameter('transaction_file'), $nameFile);

                if ($transaction->getAttachment()) {
                    unlink($this->getParameter('transaction_file') . '/' . $transaction->getAttachment());
                }

                $transaction->setAttachment($nameFile);
            }

            $entityManager->flush();
            $this->addFlash('success', 'La transaction à été modifiée avec succès !');

            return $this->redirectToRoute('app_member_transaction_index', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/transaction/edit.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
            'organization' => $organization,
            'budget' => $budget,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/transaction/{id}', name: 'app_member_transaction_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Transaction $transaction,
        EntityManagerInterface $entityManager,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $transaction->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($transaction);
            $entityManager->flush();
            $this->addFlash('success', 'La transaction à été supprimée avec succès !');
        }

        return $this->redirectToRoute('app_member_transaction_index', [
            'organizationSlug' => $organization->getSlug(),
            'budgetSlug' => $budget->getSlug(),
        ], Response::HTTP_SEE_OTHER);
    }
}
