<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crée un utilisateur : email, mot de passe, compte vérifié (true/false) et rôle.',
)]
class CreateUserCommand extends Command
{
    private const ALLOWED_ROLES = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe en clair')
            ->addArgument('verified', InputArgument::OPTIONAL, 'Compte vérifié : true ou false', 'true')
            ->addArgument('role', InputArgument::OPTIONAL, 'ROLE_USER, ROLE_ADMIN ou ROLE_SUPER_ADMIN', 'ROLE_USER')
            ->setHelp(<<<'HELP'
                Exemples :

                  <info>php bin/console app:create-user admin@arkalib.fr Motdepasse123 true ROLE_ADMIN</info>
                  <info>php bin/console app:create-user membre@arkalib.fr Motdepasse123</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Accepte true/false, 1/0, yes/no... et renvoie null si la valeur est incomprise.
        $verified = filter_var($input->getArgument('verified'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        // "admin" et "role_admin" sont acceptés au même titre que "ROLE_ADMIN".
        $role = strtoupper($input->getArgument('role'));
        if (!str_starts_with($role, 'ROLE_')) {
            $role = 'ROLE_' . $role;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('"%s" n\'est pas une adresse email valide.', $email));

            return Command::INVALID;
        }

        if (null === $verified) {
            $io->error(sprintf(
                'Le 3e argument doit valoir true ou false, "%s" reçu.',
                $input->getArgument('verified')
            ));

            return Command::INVALID;
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            $io->error(sprintf(
                'Rôle inconnu "%s". Rôles autorisés : %s.',
                $role,
                implode(', ', self::ALLOWED_ROLES)
            ));

            return Command::INVALID;
        }

        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un compte existe déjà avec l\'adresse %s.', $email));

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsVerified($verified);
        // ROLE_USER est déjà ajouté par User::getRoles(), inutile de le stocker en base.
        $user->setRoles('ROLE_USER' === $role ? [] : [$role]);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Utilisateur %s créé (rôle : %s, vérifié : %s).',
            $email,
            $role,
            $verified ? 'oui' : 'non'
        ));

        return Command::SUCCESS;
    }
}
