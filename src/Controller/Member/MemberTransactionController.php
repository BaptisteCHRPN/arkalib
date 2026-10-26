<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\BudgetLine;
use App\Entity\Organization;
use App\Entity\Transaction;
use App\Form\TransactionType;
use App\Repository\TransactionRepository;
use App\Service\BudgetBalanceCalculator;
use App\Service\RevenueProtectionService;
use App\Service\SoftDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Security\Voter\OrganizationVoter;

final class MemberTransactionController extends AbstractController
{
    use \App\Security\AssertsOwnershipTrait;
    use RevenueProtectionTrait;

    #[IsGranted('ROLE_USER')]
    #[Route('/budget/{organizationSlug}/{budgetSlug}/transaction', name: 'app_member_transaction_index', methods: ['GET'])]
    public function index(
        TransactionRepository $transactionRepository,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);
        $this->assertBudgetBelongsToOrganization($budget, $organization);

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
        $this->denyAccessUnlessGranted(OrganizationVoter::CONTRIBUTE, $organization);
        $this->assertBudgetBelongsToOrganization($budget, $organization);

        $transaction = new Transaction();
        $form = $this->createForm(TransactionType::class, $transaction, ['budget' => $budget]);
        $form->handleRequest($request);

        if ($budget->isClosed()) {
            $this->addFlash('warning', 'Ce budget est clôturé. Impossible d\'ajouter une transaction.');
            return $this->redirectToRoute('app_membre_budget_show', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

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
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $organization);
        $this->assertBudgetBelongsToOrganization($budget, $organization);
        $this->assertTransactionBelongsToBudget($transaction, $budget);

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
        $this->denyAccessUnlessGranted(OrganizationVoter::CONTRIBUTE, $organization);
        $this->assertBudgetBelongsToOrganization($budget, $organization);
        $this->assertTransactionBelongsToBudget($transaction, $budget);

        $form = $this->createForm(TransactionType::class, $transaction, ['budget' => $budget]);
        $form->handleRequest($request);

        if ($budget->isClosed()) {
            $this->addFlash('warning', 'Ce budget est clôturé. Impossible de modifier une transaction.');
            return $this->redirectToRoute('app_member_transaction_index', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }


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
        SoftDeleteService $softDeleteService,
        BudgetBalanceCalculator $balanceCalculator,
        RevenueProtectionService $revenueProtection,
        ValidatorInterface $validator,
        #[MapEntity(mapping: ['organizationSlug' => 'slug'])] Organization $organization,
        #[MapEntity(mapping: ['budgetSlug' => 'slug'])] Budget $budget,
    ): Response {
        $this->denyAccessUnlessGranted(OrganizationVoter::CONTRIBUTE, $organization);
        $this->assertBudgetBelongsToOrganization($budget, $organization);
        $this->assertTransactionBelongsToBudget($transaction, $budget);

        if ($budget->isClosed()) {
            $this->addFlash('warning', 'Ce budget est clôturé. Impossible de supprimer une transaction.');
            return $this->redirectToRoute('app_member_transaction_index', [
                'organizationSlug' => $organization->getSlug(),
                'budgetSlug' => $budget->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        $indexUrl = $this->generateUrl('app_member_transaction_index', [
            'organizationSlug' => $organization->getSlug(),
            'budgetSlug' => $budget->getSlug(),
        ]);

        if (!$this->isCsrfTokenValid('delete' . $transaction->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirect($indexUrl, Response::HTTP_SEE_OTHER);
        }

        // Photo avant / après, la suppression n'étant faite qu'en mémoire :
        // tant qu'on ne flushe pas, un refus ne laisse aucune trace en base.
        $before = $balanceCalculator->balanceOfTransactions($budget, [$transaction]);
        $softDeleteService->softDelete($transaction, $this->getUser());
        $after = $balanceCalculator->balanceOfTransactions($budget, [$transaction]);
        $protection = $revenueProtection->checkChange($budget, $before, $after);

        $stop = $this->stopIfRevenueProtected(
            $request,
            $protection,
            $budget,
            'supprimer cette transaction',
            sprintf('Suppression de la transaction %s', self::describe($transaction)),
            $indexUrl,
            $validator,
            $entityManager,
        );

        if (null !== $stop) {
            return $stop;
        }

        $entityManager->flush();
        $this->addFlash('success', 'La transaction a été déplacée dans la corbeille.');

        if ($protection->isBlocked()) {
            $this->addFlash('warning', sprintf(
                'Blocage levé : le budget « %s » est maintenant à %s € de disponible. Votre motif a été enregistré.',
                $budget->getName(),
                number_format($protection->availableAfter / 100, 2, ',', ' '),
            ));
        }

        return $this->redirect($indexUrl, Response::HTTP_SEE_OTHER);
    }

    /**
     * Ex. « du 01/06/2026 de 1 000,00 € (réf. DON-12) », pour les traces.
     */
    private static function describe(Transaction $transaction): string
    {
        return sprintf(
            'du %s de %s €%s',
            $transaction->getDate()?->format('d/m/Y'),
            number_format((float) $transaction->getAmount(), 2, ',', ' '),
            $transaction->getReference() ? sprintf(' (réf. %s)', $transaction->getReference()) : '',
        );
    }
}
