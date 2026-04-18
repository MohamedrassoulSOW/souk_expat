<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:reset-password',
    description: 'Définit un nouveau mot de passe pour un utilisateur (haché en base). À utiliser en admin / dev.',
)]
final class ResetUserPasswordCommand extends Command
{
    private const MIN_LENGTH = 6;

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
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail du compte')
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Nouveau mot de passe en clair (évitez en prod : historique shell ; préférez la saisie interactive sans cette option)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument('email'));

        if ($email === '') {
            $io->error('L’e-mail ne peut pas être vide.');

            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user === null) {
            $io->error(sprintf('Aucun utilisateur pour l’e-mail « %s ».', $email));

            return Command::FAILURE;
        }

        $plainPassword = $input->getOption('password');
        if ($plainPassword === null || $plainPassword === '') {
            if (!$input->isInteractive()) {
                $io->error('Mode non interactif : passez --password=... ou exécutez la commande dans un terminal interactif.');

                return Command::FAILURE;
            }

            $plainPassword = (string) $io->askHidden('Nouveau mot de passe');
            $confirm = (string) $io->askHidden('Confirmer le mot de passe');

            if ($plainPassword !== $confirm) {
                $io->error('Les deux saisies ne correspondent pas.');

                return Command::FAILURE;
            }
        }

        if (\strlen($plainPassword) < self::MIN_LENGTH) {
            $io->error(sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_LENGTH));

            return Command::FAILURE;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();

        $io->success(sprintf('Mot de passe mis à jour pour « %s ».', $email));

        return Command::SUCCESS;
    }
}
