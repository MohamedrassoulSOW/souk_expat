<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GoogleOAuthService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:google-oauth:configure',
    description: 'Configure GOOGLE_CLIENT_ID / SECRET dans .env.local et affiche les URI de redirection',
)]
final class GoogleOAuthConfigureCommand extends Command
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuth,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('client-id', null, InputOption::VALUE_REQUIRED, 'Google OAuth Client ID')
            ->addOption('client-secret', null, InputOption::VALUE_REQUIRED, 'Google OAuth Client Secret')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Vérifier uniquement la config actuelle');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('SoukExpat — Google OAuth');

        $localBase = rtrim($this->defaultUri, '/');
        $redirects = [
            $localBase . '/connect/google/check',
            'http://127.0.0.1:8000/connect/google/check',
            'http://127.0.0.1:8001/connect/google/check',
            'http://localhost:8000/connect/google/check',
            'http://localhost:8001/connect/google/check',
            'https://soukexpat.com/connect/google/check',
            'https://www.soukexpat.com/connect/google/check',
        ];
        $redirects = array_values(array_unique($redirects));

        $io->section('URI de redirection à autoriser dans Google Cloud Console');
        $io->listing($redirects);
        $io->writeln('Console : <info>https://console.cloud.google.com/apis/credentials</info>');
        $io->writeln('Type d’appli : <comment>Application Web</comment>');
        $io->writeln('Origines JS autorisées (ex.) : <comment>http://127.0.0.1:8001</comment> et <comment>https://soukexpat.com</comment>');

        if ($input->getOption('check') || (!$input->getOption('client-id') && !$input->getOption('client-secret'))) {
            if ($this->googleOAuth->isConfigured()) {
                $io->success('Google OAuth est configuré — le bouton apparaît sur /login et /register.');
            } else {
                $io->warning('Pas encore configuré. Exemple :');
                $io->writeln('  php bin/console app:google-oauth:configure --env=prod --client-id=XXX.apps.googleusercontent.com --client-secret=YYY');
            }

            return Command::SUCCESS;
        }

        $clientId = trim((string) $input->getOption('client-id'));
        $clientSecret = trim((string) $input->getOption('client-secret'));
        if ($clientId === '' || $clientSecret === '') {
            $io->error('Fournissez --client-id et --client-secret.');

            return Command::FAILURE;
        }

        $envLocal = $this->projectDir . '/.env.local';
        $block = "\n###> google-oauth ###\nGOOGLE_CLIENT_ID={$clientId}\nGOOGLE_CLIENT_SECRET={$clientSecret}\n###< google-oauth ###\n";

        if (is_file($envLocal)) {
            $content = (string) file_get_contents($envLocal);
            if (preg_match('/###> google-oauth ###.*?###< google-oauth ###/s', $content)) {
                $content = preg_replace('/###> google-oauth ###.*?###< google-oauth ###/s', trim($block), $content) ?? $content;
            } elseif (preg_match('/^GOOGLE_CLIENT_ID=/m', $content)) {
                $content = preg_replace('/^GOOGLE_CLIENT_ID=.*$/m', 'GOOGLE_CLIENT_ID=' . $clientId, $content) ?? $content;
                if (preg_match('/^GOOGLE_CLIENT_SECRET=/m', $content)) {
                    $content = preg_replace('/^GOOGLE_CLIENT_SECRET=.*$/m', 'GOOGLE_CLIENT_SECRET=' . $clientSecret, $content) ?? $content;
                } else {
                    $content .= "GOOGLE_CLIENT_SECRET={$clientSecret}\n";
                }
            } else {
                $content .= $block;
            }
            file_put_contents($envLocal, $content);
        } else {
            file_put_contents($envLocal, "# Local overrides\n" . $block);
        }

        $io->success('.env.local mis à jour. Videz le cache : php bin/console cache:clear --env=prod');
        $io->note('Le bouton Google s’affiche dès que GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET sont non vides.');

        return Command::SUCCESS;
    }
}
