<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\City;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:cities',
    description: 'Ajoute toutes les villes du Maroc (idempotent : ignore les noms déjà présents).',
)]
final class SeedMoroccoCitiesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CityRepository $cityRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Import des villes du Maroc');

        /** @var list<string> $names */
        $names = require \dirname(__DIR__).'/Data/morocco_cities.php';

        $existing = [];
        foreach ($this->cityRepository->findAll() as $city) {
            $key = $this->normalize($city->getName() ?? '');
            if ($key !== '') {
                $existing[$key] = true;
            }
        }

        $added = 0;
        $skipped = 0;
        $batch = 0;

        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $key = $this->normalize($name);
            if (isset($existing[$key])) {
                ++$skipped;
                continue;
            }

            $city = (new City())->setName($name);
            $this->em->persist($city);
            $existing[$key] = true;
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
            '%d ville(s) ajoutée(s), %d déjà présentes. Total en base : %d.',
            $added,
            $skipped,
            $this->cityRepository->count([]),
        ));

        return Command::SUCCESS;
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = strtr($name, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            '\'' => '', '’' => '', ' ' => '', '-' => '',
        ]);

        return $name;
    }
}
