<?php

namespace App\Command;

use App\Entity\Annonce;
use App\Repository\AnnonceRepository;
use App\Service\AnnonceDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:annonce:purge-expired',
    description: 'Supprime les annonces approuvées depuis plus de ' . Annonce::APPROVED_VISIBLE_DAYS . ' jours (durée en ligne après validation).',
)]
final class PurgeExpiredApprovedAnnoncesCommand extends Command
{
    public function __construct(
        private readonly AnnonceRepository $annonceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AnnonceDeletionService $annonceDeletionService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = new \DateTimeImmutable('-' . Annonce::APPROVED_VISIBLE_DAYS . ' days');
        $toRemove = $this->annonceRepository->findApprovedExpiredBefore($limit);

        if ($toRemove === []) {
            $io->success('Aucune annonce expirée à supprimer.');

            return Command::SUCCESS;
        }

        foreach ($toRemove as $annonce) {
            $this->annonceDeletionService->removeCompletely($this->entityManager, $annonce);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d annonce(s) expirée(s) supprimée(s) (validation antérieure au %s).', count($toRemove), $limit->format('Y-m-d H:i')));

        return Command::SUCCESS;
    }
}
