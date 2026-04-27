<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Annonce;
use App\Entity\Category;
use App\Entity\City;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
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
    name: 'app:demo:seed-users-and-annonces',
    description: 'Crée 5 comptes utilisateurs, 1 administrateur et au moins 30 annonces validées (données de démo).',
)]
final class SeedDemoUsersAndAnnoncesCommand extends Command
{
    private const string DEMO_PASSWORD = 'DemoSouk2026!';

    private const int MIN_ANNONCES = 30;

    /** @var list<string> */
    private const array USER_EMAILS = [
        'utilisateur1@souk-demo.local',
        'utilisateur2@souk-demo.local',
        'utilisateur3@souk-demo.local',
        'utilisateur4@souk-demo.local',
        'utilisateur5@souk-demo.local',
    ];

    private const string ADMIN_EMAIL = 'admin@souk-demo.local';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly CityRepository $cityRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Recréer les comptes démo même s\'ils existent déjà (supprime leurs annonces liées puis les comptes).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $demoEmails = [...self::USER_EMAILS, self::ADMIN_EMAIL];
        if (!$force) {
            foreach ($demoEmails as $email) {
                if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
                    $io->error(sprintf(
                        'Un compte démo existe déjà (%s). Supprimez ces comptes à la main ou relancez avec --force.',
                        $email,
                    ));

                    return Command::FAILURE;
                }
            }
        } else {
            $this->removeExistingDemoUsers($demoEmails);
        }

        $categories = $this->ensureCategories($io);
        $cities = $this->ensureCities($io);

        $users = $this->createDemoUsers();
        foreach ($users as $user) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $owners = array_values($users);
        $nOwners = \count($owners);
        $nCategories = \count($categories);
        $nCities = \count($cities);

        $templates = [
            ['Canapé 2 places', 'Canapé compact, bon état général. Idéal petit salon.'],
            ['Vélo ville', 'Vélo avec panier et éclairage. Révision récente.'],
            ['Micro-ondes', 'Fonctionne parfaitement, quelques traces d\'usage.'],
            ['Bureau en bois', 'Plateau 120x60 cm, pieds réglables.'],
            ['Chaise de bureau', 'Assise confortable, roulettes OK.'],
            ['Lampe sur pied', 'Abat-jour inclus, ampoule LED.'],
            ['Table basse', 'Style scandinave, plateau verre.'],
            ['Étagère 5 niveaux', 'Montage simple, stable.'],
            ['Machine à laver', '7 kg, programmes variés.'],
            ['Télé 43 pouces', 'Smart TV, télécommande d\'origine.'],
        ];

        $now = new \DateTimeImmutable();
        for ($i = 1; $i <= self::MIN_ANNONCES; ++$i) {
            $owner = $owners[($i - 1) % $nOwners];
            $category = $categories[($i - 1) % $nCategories];
            $city = $cities[($i - 1) % $nCities];
            $tpl = $templates[($i - 1) % \count($templates)];

            $title = sprintf('%s — annonce démo #%d', $tpl[0], $i);
            $description = $tpl[1] . ' Annonce générée pour tests sur Souk Expat.';

            $annonce = new Annonce();
            $annonce->setTitle($title);
            $annonce->setDescription($description);
            $annonce->setPrice(10.0 + ($i * 7.5));
            $annonce->setPhone(sprintf('06%08d', 10000000 + $i));
            $annonce->setUser($owner);
            $annonce->setCategory($category);
            $annonce->setCity($city);
            $annonce->setStatus(Annonce::STATUS_APPROVED);
            $annonce->setApprovedAt($now->modify(sprintf('-%d days', ($i - 1) % 20)));

            $this->entityManager->persist($annonce);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Comptes créés : 5 utilisateurs + 1 admin. Mot de passe commun : %s',
            self::DEMO_PASSWORD,
        ));
        $io->listing([
            'Admin : ' . self::ADMIN_EMAIL,
            ...array_map(static fn (string $e): string => 'Utilisateur : ' . $e, self::USER_EMAILS),
        ]);
        $io->note(sprintf('%d annonces validées ont été créées.', self::MIN_ANNONCES));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $emails
     */
    private function removeExistingDemoUsers(array $emails): void
    {
        foreach ($emails as $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user === null) {
                continue;
            }
            foreach ($user->getAnnonces() as $annonce) {
                $this->entityManager->remove($annonce);
            }
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }

    /**
     * @return list<Category>
     */
    private function ensureCategories(SymfonyStyle $io): array
    {
        $existing = $this->categoryRepository->findAll();
        if ($existing !== []) {
            return array_slice($existing, 0, max(1, min(5, \count($existing))));
        }

        $defs = [
            ['name' => 'Meubles', 'slug' => 'meubles'],
            ['name' => 'Électroménager', 'slug' => 'electromenager'],
            ['name' => 'Véhicules & accessoires', 'slug' => 'vehicules-accessoires'],
        ];
        $list = [];
        foreach ($defs as $def) {
            $c = new Category();
            $c->setName($def['name']);
            $c->setSlug($def['slug']);
            $this->entityManager->persist($c);
            $list[] = $c;
        }
        $this->entityManager->flush();
        $io->note('Catégories de base créées (base vide).');

        return $list;
    }

    /**
     * @return list<City>
     */
    private function ensureCities(SymfonyStyle $io): array
    {
        $existing = $this->cityRepository->findAll();
        if ($existing !== []) {
            return array_slice($existing, 0, max(1, min(5, \count($existing))));
        }

        $names = ['Casablanca', 'Rabat', 'Marrakech'];
        $list = [];
        foreach ($names as $name) {
            $city = new City();
            $city->setName($name);
            $this->entityManager->persist($city);
            $list[] = $city;
        }
        $this->entityManager->flush();
        $io->note('Villes de base créées (base vide).');

        return $list;
    }

    /**
     * @return list<User>
     */
    private function createDemoUsers(): array
    {
        $firstNames = ['Amine', 'Sara', 'Youssef', 'Lina', 'Omar', 'Admin'];
        $lastNames = ['Alami', 'Benali', 'Idrissi', 'Cherkaoui', 'Fassi', 'Souk'];

        $users = [];
        foreach (self::USER_EMAILS as $idx => $email) {
            $u = new User();
            $u->setEmail($email);
            $u->setFirstName($firstNames[$idx]);
            $u->setLastName($lastNames[$idx]);
            $u->setRoles(['ROLE_USER']);
            $u->setPassword($this->passwordHasher->hashPassword($u, self::DEMO_PASSWORD));
            $users[] = $u;
        }

        $admin = new User();
        $admin->setEmail(self::ADMIN_EMAIL);
        $admin->setFirstName($firstNames[5]);
        $admin->setLastName($lastNames[5]);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, self::DEMO_PASSWORD));
        $users[] = $admin;

        return $users;
    }
}
