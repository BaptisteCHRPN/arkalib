<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\RevenueProtectionOverride;
use App\Entity\User;
use App\Model\RevenueProtectionResult;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Suite à donner quand une opération fait perdre une recette déjà réaffectée
 * (verdict de RevenueProtectionService::checkChange()).
 *
 * L'appelant n'a encore rien flushé : un refus laisse la base intacte.
 */
trait RevenueProtectionTrait
{
    /**
     * Renvoie la réponse qui arrête l'opération, ou null si elle peut être
     * enregistrée :
     * - opération autorisée : null ;
     * - utilisateur non admin : refus, avec le message qui nomme les réaffectations ;
     * - admin sans motif valide : écran de confirmation (motif obligatoire) ;
     * - admin avec motif valide : trace persistée, null (l'appelant flushe).
     *
     * L'écran de confirmation renvoie les champs simples de la requête
     * (ex. le jeton CSRF) : il convient aux formulaires de suppression.
     *
     * @param string   $action         ce que l'utilisateur voulait faire, ex. « supprimer cette transaction »
     * @param string   $operation      description figée pour la trace
     * @param object[] $changedEntities objets modifiés en mémoire par l'opération
     */
    private function stopIfRevenueProtected(
        Request $request,
        RevenueProtectionResult $result,
        Budget $budget,
        string $action,
        string $operation,
        string $cancelUrl,
        array $changedEntities,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): ?Response {
        if (!$result->isBlocked()) {
            return null;
        }

        $response = $this->revenueProtectionResponse($request, $result, $budget, $action, $operation, $cancelUrl, $validator, $entityManager);

        if (null !== $response) {
            // Opération arrêtée : on relit ces objets depuis la base pour
            // annuler la modification faite en mémoire. Sinon, un flush
            // ultérieur de la même requête l'enregistrerait quand même (ex.
            // AdminActionLogSubscriber, qui flushe en fin de requête pour un
            // administrateur global).
            foreach ($changedEntities as $entity) {
                $entityManager->refresh($entity);
            }
        }

        return $response;
    }

    private function revenueProtectionResponse(
        Request $request,
        RevenueProtectionResult $result,
        Budget $budget,
        string $action,
        string $operation,
        string $cancelUrl,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): ?Response {
        $message = $result->message($action);

        if (!$this->isGranted(OrganizationVoter::ADMINISTER, $budget->getOrganization())) {
            $this->addFlash('error', $message . ' Seul un administrateur de l\'association peut passer outre.');

            return $this->redirect($cancelUrl, Response::HTTP_SEE_OTHER);
        }

        $payload = $request->getPayload();
        $errors = [];

        if ($payload->has('force_reason')) {
            $violations = $this->forceRevenueProtection($budget, $result, $payload->getString('force_reason'), $operation, $validator, $entityManager);

            if (0 === count($violations)) {
                return null;
            }

            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
        }

        $fields = array_filter(
            $payload->all(),
            fn($value, string $name) => is_scalar($value) && 'force_reason' !== $name,
            ARRAY_FILTER_USE_BOTH,
        );

        // 422 : Turbo n'affiche la réponse d'un formulaire que si c'est une
        // redirection ou une erreur.
        return $this->render('member/revenue_protection/confirm.html.twig', [
            'organization'   => $budget->getOrganization(),
            'budget'         => $budget,
            'message'        => $message,
            'availableAfter' => $result->availableAfter / 100,
            'operation'      => $operation,
            'errors'         => $errors,
            'formAction'     => $request->getRequestUri(),
            'fields'         => $fields,
            'cancelUrl'      => $cancelUrl,
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * Variante pour un formulaire de modification : le blocage s'affiche au-dessus
     * du formulaire, qui garde la saisie. Un admin y trouve le champ
     * force_reason (option allow_force du formulaire).
     *
     * @return string|null null si l'opération peut être enregistrée (autorisée, ou
     *                     forcée par un admin : trace persistée), sinon le message à afficher
     */
    private function revenueProtectionMessageForForm(
        Request $request,
        FormInterface $form,
        RevenueProtectionResult $result,
        Budget $budget,
        string $action,
        string $operation,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): ?string {
        if (!$result->isBlocked()) {
            return null;
        }

        $message = $result->message($action);

        if (!$this->isGranted(OrganizationVoter::ADMINISTER, $budget->getOrganization()) || !$form->has('force_reason')) {
            return $message . ' Seul un administrateur de l\'association peut passer outre.';
        }

        // Le champ n'est affiché qu'après un premier blocage. Sa présence dans
        // la requête dit si l'admin a tenté de passer outre ; sa valeur seule
        // ne le dit pas (un motif vide arrive comme null).
        if (array_key_exists('force_reason', $request->getPayload()->all($form->getName()))) {
            $reason = (string) $form->get('force_reason')->getData();
            $violations = $this->forceRevenueProtection($budget, $result, $reason, $operation, $validator, $entityManager);

            if (0 === count($violations)) {
                return null;
            }

            foreach ($violations as $violation) {
                $form->get('force_reason')->addError(new FormError($violation->getMessage()));
            }
        }

        return sprintf(
            '%s En tant qu\'administrateur, vous pouvez passer outre en indiquant un motif ci-dessous : le budget « %s » passera alors à %s € de disponible.',
            $message,
            $budget->getName(),
            number_format($result->availableAfter / 100, 2, ',', ' '),
        );
    }

    /**
     * Réaffiche un formulaire bloqué, la saisie conservée, puis annule en
     * mémoire la modification (même raison que dans stopIfRevenueProtected()).
     *
     * @param object[] $changedEntities
     */
    private function renderBlockedForm(string $template, array $parameters, FormInterface $form, array $changedEntities, EntityManagerInterface $entityManager): Response
    {
        // La vue est construite avant le refresh : elle garde ce que
        // l'utilisateur a saisi.
        $parameters['form'] = $form->createView();

        foreach ($changedEntities as $entity) {
            $entityManager->refresh($entity);
        }

        return $this->render($template, $parameters, new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * Enregistre la trace d'un admin qui passe outre, si son motif est valide.
     */
    private function forceRevenueProtection(
        Budget $budget,
        RevenueProtectionResult $result,
        string $reason,
        string $operation,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): ConstraintViolationListInterface {
        /** @var User $user */
        $user = $this->getUser();
        $override = new RevenueProtectionOverride($budget, $user, $reason, $operation, $result);
        $violations = $validator->validate($override);

        if (0 === count($violations)) {
            $entityManager->persist($override);
        }

        return $violations;
    }
}
