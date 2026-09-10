<?php

namespace App\EventSubscriber;

use App\Entity\AdminActionLog;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enregistre les interventions des administrateurs globaux.
 *
 * Écouter les requêtes plutôt que d'appeler un service depuis chaque action :
 * un journal ne vaut que par sa complétude, et trente-deux points d'appel à ne
 * pas oublier — plus ceux de demain — n'offrent aucune garantie. Le prix à
 * payer est que l'on enregistre l'intention, pas le détail métier.
 *
 * Le déclenchement se fait après l'envoi de la réponse : le client n'attend
 * pas l'écriture du journal.
 */
class AdminActionLogSubscriber implements EventSubscriberInterface
{
    /** Les routes d'authentification ne sont pas des interventions. */
    private const IGNORED_ROUTES = ['app_login', 'app_logout'];

    public function __construct(
        private Security $security,
        private EntityManagerInterface $em,
        private OrganizationRepository $organizationRepository,
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        // Seules les actions qui changent quelque chose sont journalisées.
        if ($request->isMethodSafe()) {
            return;
        }

        $routeName = (string) $request->attributes->get('_route');

        if ('' === $routeName
            || str_starts_with($routeName, '_')
            || in_array($routeName, self::IGNORED_ROUTES, true)
        ) {
            return;
        }

        $actor = $this->security->getUser();

        if (!$actor instanceof User || !$this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $organization = $this->resolveOrganization($request);

        // Tenir la comptabilité de sa propre association n'est pas une
        // intervention de support : sans ce filtre, le journal se noierait
        // sous la saisie courante de l'exploitant.
        if (null !== $organization && null !== $organization->getMembershipFor($actor)) {
            return;
        }

        $this->write($request, $event->getResponse()->getStatusCode(), $actor, $organization, $routeName);
    }

    private function write(
        Request $request,
        int $statusCode,
        User $actor,
        ?Organization $organization,
        string $routeName,
    ): void {
        if (!$this->em->isOpen()) {
            $this->logger->error('Journal d\'administration : gestionnaire fermé, intervention non enregistrée.', [
                'route' => $routeName,
            ]);

            return;
        }

        $log = (new AdminActionLog())
            ->setActor($actor)
            ->setAction($this->label($routeName))
            ->setRouteName($routeName)
            ->setMethod($request->getMethod())
            ->setOrganization($organization)
            ->setTarget($this->describeTarget($request))
            ->setStatusCode($statusCode)
            ->setIpAddress($request->getClientIp());

        try {
            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable $exception) {
            // Une panne du journal ne doit pas casser l'application, mais elle
            // ne doit pas non plus passer inaperçue.
            $this->logger->error('Journal d\'administration : écriture impossible.', [
                'route' => $routeName,
                'exception' => $exception,
            ]);
        }
    }

    private function resolveOrganization(Request $request): ?Organization
    {
        $slug = $request->attributes->get('organizationSlug');

        if (!is_string($slug) || '' === $slug) {
            return null;
        }

        return $this->organizationRepository->findOneBy(['slug' => $slug]);
    }

    /**
     * L'objet visé, quand la route permet de le nommer.
     */
    private function describeTarget(Request $request): ?string
    {
        $parts = [];

        if (is_string($budgetSlug = $request->attributes->get('budgetSlug'))) {
            $parts[] = sprintf('budget « %s »', $budgetSlug);
        }

        if (null !== ($id = $request->attributes->get('id'))) {
            $parts[] = '#' . $id;
        }

        return [] === $parts ? null : implode(' ', $parts);
    }

    private function label(string $routeName): string
    {
        return match ($routeName) {
            'app_admin_user_new' => 'Créer un compte',
            'app_admin_user_edit' => 'Modifier un compte',
            'app_admin_user_resend_verification' => 'Renvoyer l\'e-mail de vérification',
            'app_admin_user_send_password_reset' => 'Envoyer un lien de réinitialisation',
            'app_admin_user_toggle_admin' => 'Modifier les droits d\'administrateur',
            'app_admin_user_anonymize' => 'Anonymiser un compte',

            'app_membre_organization_new' => 'Créer une organisation',
            'app_member_organization_edit' => 'Modifier une organisation',
            'app_member_organization_delete' => 'Supprimer définitivement une organisation',

            'app_member_budget_new' => 'Créer un budget',
            'app_membre_duplicate_budget' => 'Dupliquer un budget',
            'app_member_budget_close' => 'Clôturer un budget',
            'app_member_budget_reopen' => 'Rouvrir un budget',
            'app_member_budget_delete' => 'Mettre un budget à la corbeille',

            'app_budget_line_new' => 'Créer une ligne budgétaire',
            'app_member_budget_line_edit' => 'Modifier une ligne budgétaire',
            'app_soft_delete_budget_line' => 'Mettre une ligne à la corbeille',

            'app_category_new' => 'Créer une catégorie',
            'app_member_category_delete' => 'Supprimer une catégorie',

            'app_member_transaction_new' => 'Créer une transaction',
            'app_member_transaction_edit' => 'Modifier une transaction',
            'app_member_transaction_delete' => 'Mettre une transaction à la corbeille',

            'app_member_trash_restore' => 'Restaurer depuis la corbeille',
            'app_member_trash_hard_delete' => 'Supprimer définitivement',

            'app_invitation_send' => 'Envoyer une invitation',
            'app_invitation_revoke' => 'Révoquer une invitation',
            'app_member_membership_change_role' => 'Changer le rôle d\'un membre',
            'app_member_membership_remove' => 'Retirer un membre',
            'app_member_membership_leave' => 'Quitter une organisation',

            'app_member_user_edit' => 'Modifier un profil',
            'app_member_user_change_email' => 'Changer une adresse e-mail',
            'app_user_delete' => 'Supprimer un compte',

            // Une route inconnue est journalisée sous son nom technique plutôt
            // que passée sous silence : mieux vaut une entrée aride qu'un trou.
            default => $routeName,
        };
    }
}
