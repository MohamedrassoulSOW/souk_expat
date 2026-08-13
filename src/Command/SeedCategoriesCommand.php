<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:categories',
    description: 'Ajoute un large jeu de catégories marketplace (idempotent : ignore les slugs déjà présents).',
)]
final class SeedCategoriesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Import des catégories');

        /** @var list<array{name: string, slug: string, icon: string}> $rows */
        $rows = require \dirname(__DIR__).'/Data/morocco_categories.php';

        $existingSlugs = [];
        foreach ($this->categoryRepository->findAll() as $category) {
            $slug = mb_strtolower((string) $category->getSlug(), 'UTF-8');
            if ($slug !== '') {
                $existingSlugs[$slug] = true;
            }
        }

        $added = 0;
        $skipped = 0;
        $batch = 0;

        foreach ($rows as $row) {
            $slug = mb_strtolower(trim($row['slug']), 'UTF-8');
            $name = trim($row['name']);
            if ($slug === '' || $name === '') {
                continue;
            }

            if (isset($existingSlugs[$slug])) {
                ++$skipped;
                continue;
            }

            $category = (new Category())
                ->setName($name)
                ->setSlug($slug)
                ->setIcon($row['icon'] ?? null);

            $this->em->persist($category);
            $existingSlugs[$slug] = true;
            ++$added;
            ++$batch;

            if ($batch >= 50) {
                $this->em->flush();
                $batch = 0;
            }
        }

        if ($batch > 0) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%d catégorie(s) ajoutée(s), %d déjà présentes. Total en base : %d.',
            $added,
            $skipped,
            $this->categoryRepository->count([]),
        ));

        return Command::SUCCESS;
    }
}
