<?php

namespace App\Controller\Member;

use App\Entity\Budget;
use App\Entity\RevenueProtectionOverride;
use App\Entity\User;
use App\Model\RevenueProtectionResult;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * @param string $action    ce que l'utilisateur voulait faire, ex. « supprimer cette transaction »
     * @param string $operation description figée pour la trace
     */
    private function stopIfRevenueProtected(
        Request $request,
        RevenueProtectionResult $result,
        Budget $budget,
        string $action,
        string $operation,
        string $cancelUrl,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
    ): ?Response {
        if (!$result->isBlocked()) {
            return null;
        }

        $message = $result->message($action);

        if (!$this->isGranted(OrganizationVoter::ADMINISTER, $budget->getOrganization())) {
            $this->addFlash('error', $message . ' Seul un administrateur de l\'association peut passer outre.');

            return $this->redirect($cancelUrl, Response::HTTP_SEE_OTHER);
        }

        $payload = $request->getPayload();
        $errors = [];

        if ($payload->has('force_reason')) {
            /** @var User $user */
            $user = $this->getUser();
            $override = new RevenueProtectionOverride($budget, $user, $payload->getString('force_reason'), $operation, $result);
            $violations = $validator->validate($override);

            if (0 === count($violations)) {
                $entityManager->persist($override);

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
}
