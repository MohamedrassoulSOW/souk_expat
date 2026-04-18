<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create-editor',
    description: 'Crée un compte éditeur (ROLE_EDITOR) : accès back-office, sauf gestion des utilisateurs (ROLE_ADMIN).',
)]
final class CreateEditorUserCommand extends Command
{
    private const MIN_LEN = 8;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-mail (identifiant de connexion)')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Prénom')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Nom')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Mot de passe (saisie masquée si omis en interactif)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getOption('email'));
        $first = trim((string) $input->getOption('first-name'));
        $last = trim((string) $input->getOption('last-name'));

        if ($email === '' || $first === '' || $last === '') {
            $io->error('Options requises : --email, --first-name, --last-name.');

            return Command::FAILURE;
        }

        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            $io->error(sprintf('Un compte existe déjà pour « %s ». Autre e-mail, ou gérez les rôles via la base / un compte super-admin.', $email));

            return Command::FAILURE;
        }

        $plain = $input->getOption('password');
        if (!\is_string($plain) || $plain === '') {
            $plain = $io->askHidden('Mot de passe (min. ' . self::MIN_LEN . ' caractères)', function (string $p): string {
                if (strlen($p) < self::MIN_LEN) {
                    throw new \InvalidArgumentException('Trop court.');
                }

                return $p;
            });
        } elseif (strlen($plain) < self::MIN_LEN) {
            $io->error('Le mot de passe est trop court.');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($first);
        $user->setLastName($last);
        $user->setRoles([User::ROLE_EDITOR]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Compte éditeur créé : ' . $email);
        $io->note('Back-office (annonces, contacts, slider, etc.) : oui. Menu « Utilisateurs » : non (réservé à ROLE_ADMIN).');

        return Command::SUCCESS;
    }
}
