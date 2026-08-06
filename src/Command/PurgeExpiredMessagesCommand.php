<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MessageRetentionService;
use App\Service\SiteSettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:messages:purge-expired',
    description: 'Supprime les messages de discussion de plus de 30 jours (photos + fils vides)',
)]
final class PurgeExpiredMessagesCommand extends Command
{
    public function __construct(
        private readonly MessageRetentionService $retentionService,
        private readonly SiteSettingsService $settingsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre de jours de conservation',
                (string) MessageRetentionService::RETENTION_DAYS,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simuler sans supprimer',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Purge des messages de discussion');
        $io->text(sprintf(
            'Conservation : %d jour%s%s',
            $days,
            $days > 1 ? 's' : '',
            $dryRun ? ' (simulation)' : '',
        ));

        // Respect site setting: skip purge if disabled in admin
        $settings = $this->settingsService->get();
        if (!$settings->isPurgeMessagesEnabled()) {
            $io->warning('La purge automatique des messages est désactivée dans les paramètres du site. Aucune action réalisée.');
            return Command::SUCCESS;
        }

        $result = $this->retentionService->purgeExpired($days, $dryRun);

        $io->listing([
            'Limite : messages créés avant ' . $result['cutoff']->format('Y-m-d H:i:s'),
            'Messages ' . ($dryRun ? 'à supprimer' : 'supprimés') . ' : ' . $result['messages'],
            'Fichiers photo ' . ($dryRun ? 'à supprimer' : 'supprimés') . ' : ' . $result['files'],
            'Conversations vides ' . ($dryRun ? 'à supprimer' : 'supprimées') . ' : ' . $result['threads'],
        ]);

        if ($result['messages'] === 0 && $result['threads'] === 0) {
            $io->success('Rien à purger.');
        } elseif ($dryRun) {
            $io->warning('Simulation terminée — aucune donnée modifiée.');
        } else {
            $io->success('Purge terminée.');
        }

        return Command::SUCCESS;
    }
}
