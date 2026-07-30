<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Annonce;
use App\Entity\AnnonceImage;
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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:demo:seed-users-and-annonces',
    description: 'Crée 10 comptes utilisateurs et au moins 10 annonces validées par compte (avec images téléchargées).',
)]
final class SeedDemoUsersAndAnnoncesCommand extends Command
{
    private const string DEMO_PASSWORD = 'DemoSouk2026!';

    private const int USER_COUNT = 10;

    private const int ANNONCES_PER_USER = 10;

    /** @var list<string> */
    private const array USER_EMAILS = [
        'utilisateur1@souk-demo.local',
        'utilisateur2@souk-demo.local',
        'utilisateur3@souk-demo.local',
        'utilisateur4@souk-demo.local',
        'utilisateur5@souk-demo.local',
        'utilisateur6@souk-demo.local',
        'utilisateur7@souk-demo.local',
        'utilisateur8@souk-demo.local',
        'utilisateur9@souk-demo.local',
        'utilisateur10@souk-demo.local',
    ];

    private const string ADMIN_EMAIL = 'admin@souk-demo.local';

    /**
     * Images Unsplash libres (hotlink CDN) — une URL par type de produit.
     *
     * @var list<array{title: string, description: string, image: string}>
     */
    private const array PRODUCT_TEMPLATES = [
        [
            'title' => 'Canapé 2 places',
            'description' => 'Canapé compact, bon état général. Idéal petit salon.',
            'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Vélo ville',
            'description' => 'Vélo avec panier et éclairage. Révision récente.',
            'image' => 'https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Micro-ondes',
            'description' => 'Fonctionne parfaitement, quelques traces d\'usage.',
            'image' => 'https://images.unsplash.com/photo-1585659722983-3a675dabf23d?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Bureau en bois',
            'description' => 'Plateau 120x60 cm, pieds réglables.',
            'image' => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Chaise de bureau',
            'description' => 'Assise confortable, roulettes OK.',
            'image' => 'https://images.unsplash.com/photo-1580480055273-228ff5388ef8?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Lampe sur pied',
            'description' => 'Abat-jour inclus, ampoule LED.',
            'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Table basse',
            'description' => 'Style scandinave, plateau verre.',
            'image' => 'https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Étagère 5 niveaux',
            'description' => 'Montage simple, stable.',
            'image' => 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Machine à laver',
            'description' => '7 kg, programmes variés.',
            'image' => 'https://images.unsplash.com/photo-1626806787461-102c1bfaaea1?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Télé 43 pouces',
            'description' => 'Smart TV, télécommande d\'origine.',
            'image' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Réfrigérateur',
            'description' => 'Classe A+, compartiment freezer.',
            'image' => 'https://images.unsplash.com/photo-1571175443880-49e1d25b2bc5?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Aspirateur',
            'description' => 'Sac inclus, filtre HEPA.',
            'image' => 'https://images.unsplash.com/photo-1558317374-067fb5f30001?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Matelas 140x190',
            'description' => 'Fermeté moyenne, housse lavable.',
            'image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Guitare acoustique',
            'description' => 'Cordes neuves, housse fournie.',
            'image' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?w=800&h=600&fit=crop&q=80',
        ],
        [
            'title' => 'Trottinette électrique',
            'description' => 'Autonomie environ 20 km.',
            'image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&h=600&fit=crop&q=80',
        ],
    ];

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly CityRepository $cityRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        ?HttpClientInterface $httpClient = null,
    ) {
        parent::__construct();
        $this->httpClient = $httpClient ?? HttpClient::create([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'SoukExpatDemoSeeder/1.0',
                'Accept' => 'image/*,*/*',
            ],
        ]);
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

        $uploadDir = $this->projectDir . '/public/uploads/annonces';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $io->error('Impossible de créer le dossier uploads/annonces.');

            return Command::FAILURE;
        }

        $categories = $this->ensureCategories($io);
        $cities = $this->ensureCities($io);

        $users = $this->createDemoUsers();
        foreach ($users as $user) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $regularUsers = array_slice($users, 0, self::USER_COUNT);
        $nCategories = \count($categories);
        $nCities = \count($cities);
        $nTemplates = \count(self::PRODUCT_TEMPLATES);

        $now = new \DateTimeImmutable();
        $annonceIndex = 0;
        $imagesDownloaded = 0;
        $imagesFailed = 0;

        $io->progressStart(self::USER_COUNT * self::ANNONCES_PER_USER);

        foreach ($regularUsers as $ownerIndex => $owner) {
            for ($j = 1; $j <= self::ANNONCES_PER_USER; ++$j) {
                ++$annonceIndex;
                $tpl = self::PRODUCT_TEMPLATES[($annonceIndex - 1) % $nTemplates];
                $category = $categories[($annonceIndex - 1) % $nCategories];
                $city = $cities[($annonceIndex - 1) % $nCities];

                $title = sprintf('%s — démo u%d #%d', $tpl['title'], $ownerIndex + 1, $j);
                $description = $tpl['description'] . ' Annonce générée pour tests sur Souk Expat.';

                $annonce = new Annonce();
                $annonce->setTitle($title);
                $annonce->setDescription($description);
                $annonce->setPrice(10.0 + ($annonceIndex * 7.5));
                $annonce->setPhone('');
                $annonce->setUser($owner);
                $annonce->setCategory($category);
                $annonce->setCity($city);
                $annonce->setStatus(Annonce::STATUS_APPROVED);
                $annonce->setApprovedAt($now->modify(sprintf('-%d days', ($annonceIndex - 1) % 20)));

                $this->entityManager->persist($annonce);

                $filename = $this->downloadProductImage($tpl['image'], $uploadDir, $annonceIndex);
                if ($filename !== null) {
                    $annonceImage = new AnnonceImage();
                    $annonceImage->setImadeName($filename);
                    $annonceImage->setAnnonce($annonce);
                    $this->entityManager->persist($annonceImage);
                    ++$imagesDownloaded;
                } else {
                    ++$imagesFailed;
                }

                $io->progressAdvance();
            }

            $this->entityManager->flush();
        }

        $io->progressFinish();

        $io->success(sprintf(
            'Comptes créés : %d utilisateurs + 1 admin. Mot de passe commun : %s',
            self::USER_COUNT,
            self::DEMO_PASSWORD,
        ));
        $io->listing([
            'Admin : ' . self::ADMIN_EMAIL,
            ...array_map(static fn (string $e): string => 'Utilisateur : ' . $e, self::USER_EMAILS),
        ]);
        $io->note(sprintf(
            '%d annonces validées créées (%d par utilisateur). Images téléchargées : %d%s',
            self::USER_COUNT * self::ANNONCES_PER_USER,
            self::ANNONCES_PER_USER,
            $imagesDownloaded,
            $imagesFailed > 0 ? sprintf(' (%d échecs)', $imagesFailed) : '',
        ));

        return Command::SUCCESS;
    }

    private function downloadProductImage(string $url, string $uploadDir, int $index): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $content = $response->getContent();
            if ($content === '') {
                return null;
            }

            $contentType = $response->getHeaders(false)['content-type'][0] ?? 'image/jpeg';
            $ext = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'gif') => 'gif',
                default => 'jpg',
            };

            $filename = sprintf('demo_%d_%s.%s', $index, bin2hex(random_bytes(4)), $ext);
            $path = $uploadDir . '/' . $filename;
            if (file_put_contents($path, $content) === false) {
                return null;
            }

            return $filename;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<string> $emails
     */
    private function removeExistingDemoUsers(array $emails): void
    {
        $uploadDir = $this->projectDir . '/public/uploads/annonces/';

        foreach ($emails as $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user === null) {
                continue;
            }
            foreach ($user->getAnnonces() as $annonce) {
                foreach ($annonce->getAnnonceImages() as $image) {
                    $name = $image->getImadeName();
                    if ($name !== null && $name !== '') {
                        $path = $uploadDir . $name;
                        if (is_file($path)) {
                            @unlink($path);
                        }
                    }
                    $this->entityManager->remove($image);
                }
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
        $firstNames = ['Amine', 'Sara', 'Youssef', 'Lina', 'Omar', 'Nadia', 'Karim', 'Ines', 'Hicham', 'Salma'];
        $lastNames = ['Alami', 'Benali', 'Idrissi', 'Cherkaoui', 'Fassi', 'Tazi', 'Bennani', 'Amrani', 'Kadiri', 'Lahsini'];

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
        $admin->setFirstName('Admin');
        $admin->setLastName('Souk');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, self::DEMO_PASSWORD));
        $users[] = $admin;

        return $users;
    }
}
