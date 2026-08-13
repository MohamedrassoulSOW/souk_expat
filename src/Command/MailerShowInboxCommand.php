<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:mailer:show-inbox',
    description: 'Affiche le dernier e-mail enregistré (transport fichier dev : var/mail/)',
)]
final class MailerShowInboxCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'mail';

        if (!is_dir($dir)) {
            $io->warning('Dossier var/mail/ absent. En dev, les e-mails y sont écrits via DevFileTransport.');

            return Command::FAILURE;
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.eml') ?: glob($dir . DIRECTORY_SEPARATOR . '*');
        if ($files === false || $files === []) {
            $io->writeln('Aucun e-mail dans var/mail/. Demandez un reset mot de passe ou lancez app:mailer:smoke-test.');

            return Command::SUCCESS;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $latest = $files[0];
        $content = (string) file_get_contents($latest);

        $io->title('Dernier e-mail — ' . basename($latest));
        $io->writeln($content);

        if (preg_match('#https?://[^\s<>"]+/reset-password/reset/[^\s<>"\'\)]+#', $content, $m)) {
            $io->section('Lien reset mot de passe');
            $io->writeln($m[0]);
        }

        return Command::SUCCESS;
    }
}
