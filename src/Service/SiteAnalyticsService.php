<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Annonce;
use App\Repository\AnnonceRepository;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use App\Repository\ContactRepository;
use App\Repository\MessageRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Agrégats pour le rapport analytics admin.
 */
final class SiteAnalyticsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly AnnonceRepository $annonceRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly CityRepository $cityRepository,
        private readonly ContactRepository $contactRepository,
        private readonly MessageRepository $messageRepository,
        private readonly ThreadRepository $threadRepository,
    ) {
    }

    /**
     * @param 'months'|'days' $unit
     * @return array<string, mixed>
     */
    public function buildReport(int $value = 12, string $unit = 'months'): array
    {
        $unit = $unit === 'days' ? 'days' : 'months';

        if ($unit === 'days') {
            $value = max(1, min(3650, $value));
            $since = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $value))->setTime(0, 0);
            $monthsApprox = max(1, (int) ceil($value / 30));
            $periodLabel = $value . ' jour' . ($value > 1 ? 's' : '');
        } else {
            $value = max(1, min(60, $value));
            $since = new \DateTimeImmutable(sprintf('first day of -%d months 00:00:00', $value - 1));
            $monthsApprox = $value;
            $periodLabel = $value . ' mois';
        }

        $pending = $this->annonceRepository->count(['status' => Annonce::STATUS_PENDING]);
        $approved = $this->annonceRepository->count(['status' => Annonce::STATUS_APPROVED]);
        $rejected = $this->annonceRepository->count(['status' => Annonce::STATUS_REJECTED]);
        $draft = $this->annonceRepository->count(['status' => Annonce::STATUS_DRAFT]);

        $usersTotal = $this->userRepository->count([]);
        $sellersActive = $this->countActiveSellers();
        $usersWithWhatsapp = $this->countUsersWithWhatsapp();

        $messagesTotal = $this->messageRepository->count([]);
        $threadsTotal = $this->threadRepository->count([]);
        $contactsOpen = $this->contactRepository->count(['isProcessed' => false]);
        $contactsDone = $this->contactRepository->count(['isProcessed' => true]);

        $avgPrice = $this->avgApprovedPrice();
        $priceRange = $this->approvedPriceRange();

        $annoncesByMonth = $this->countAnnoncesByMonth($since);
        $messagesByMonth = $this->countMessagesByMonth($since);
        $contactsByMonth = $this->countContactsByMonth($since);
        $approvalsByMonth = $this->countApprovalsByMonth($since);

        $monthLabels = $this->monthLabels($since);

        return [
            'generatedAt' => new \DateTimeImmutable(),
            'months' => $monthsApprox,
            'periodValue' => $value,
            'periodUnit' => $unit,
            'periodLabel' => $periodLabel,
            'since' => $since,
            'kpis' => [
                'users' => $usersTotal,
                'sellersActive' => $sellersActive,
                'usersWithWhatsapp' => $usersWithWhatsapp,
                'annoncesTotal' => $pending + $approved + $rejected + $draft,
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'draft' => $draft,
                'messages' => $messagesTotal,
                'threads' => $threadsTotal,
                'contactsOpen' => $contactsOpen,
                'contactsDone' => $contactsDone,
                'categories' => $this->categoryRepository->count([]),
                'cities' => $this->cityRepository->count([]),
                'avgPrice' => $avgPrice,
                'minPrice' => $priceRange['min'],
                'maxPrice' => $priceRange['max'],
                'approvalRate' => ($approved + $rejected) > 0
                    ? round(100 * $approved / ($approved + $rejected), 1)
                    : null,
            ],
            'charts' => [
                'monthLabels' => $monthLabels,
                'annoncesByMonth' => $this->alignSeries($monthLabels, $annoncesByMonth),
                'messagesByMonth' => $this->alignSeries($monthLabels, $messagesByMonth),
                'contactsByMonth' => $this->alignSeries($monthLabels, $contactsByMonth),
                'approvalsByMonth' => $this->alignSeries($monthLabels, $approvalsByMonth),
                'statusLabels' => ['Validées', 'En attente', 'Refusées', 'Brouillons'],
                'statusValues' => [$approved, $pending, $rejected, $draft],
                'categories' => $this->annoncesByCategory(8),
                'cities' => $this->annoncesByCity(8),
                'topSellers' => $this->topSellers(10),
                'contactsSplit' => [
                    'labels' => ['À traiter', 'Traités'],
                    'values' => [$contactsOpen, $contactsDone],
                ],
            ],
            'tables' => [
                'topSellers' => $this->topSellersDetailed(10),
                'recentAnnonces' => $this->recentAnnonces(8),
                'busiestThreads' => $this->busiestThreads(8),
            ],
            'insights' => $this->buildInsights(
                $usersTotal,
                $sellersActive,
                $approved,
                $pending,
                $rejected,
                $messagesTotal,
                $threadsTotal,
                $contactsOpen,
                $contactsDone,
                $avgPrice,
                $periodLabel,
            ),
            'explanations' => $this->buildExplanations($periodLabel, $unit, $value, $since),
        ];
    }

    /**
     * Textes d’aide pour comprendre chaque bloc du rapport.
     *
     * @return array<string, string>
     */
    private function buildExplanations(string $periodLabel, string $unit, int $value, \DateTimeImmutable $since): array
    {
        $periodDetail = $unit === 'days'
            ? sprintf('les %d derniers jours', $value)
            : sprintf('les %d derniers mois', $value);

        return [
            'intro' => sprintf(
                'Ce rapport regroupe l’état du marketplace SoukExpat et l’activité observée sur %s (depuis le %s). '
                . 'Les indicateurs « stock » (utilisateurs, annonces validées, etc.) reflètent la situation actuelle de la base ; '
                . 'les tendances (graphiques mensuels) se limitent à la période choisie.',
                $periodDetail,
                $since->format('d/m/Y')
            ),
            'howToRead' => 'Comment lire ce rapport : commencez par les points clés (insights), puis les indicateurs chiffrés, '
                . 'ensuite les diagrammes pour repérer les hausses/baisses, et enfin les tableaux pour identifier les vendeurs, '
                . 'conversations ou annonces à suivre. Exportez en PDF/Word pour partager une version commentée.',
            'insights' => 'Synthèse automatique : messages prioritaires pour la modération, l’engagement vendeurs et le support.',
            'kpis' => 'Vue d’ensemble du site à l’instant T. Chaque carte combine un chiffre principal et un détail utile '
                . '(ex. vendeurs actifs, annonces en attente). Utile pour un briefing rapide ou un reporting hebdomadaire.',
            'trends' => sprintf(
                'Évolution mois par mois sur %s : annonces créées, validations admin, messages chat et formulaires contact. '
                . 'Une hausse des créations sans validations peut indiquer un retard de modération ; '
                . 'une hausse des messages signale plus d’échanges acheteurs–vendeurs.',
                $periodLabel
            ),
            'status' => 'Répartition de toutes les annonces selon leur statut actuel (validées, en attente, refusées, brouillons). '
                . 'Un volume élevé « en attente » demande une action de modération ; beaucoup de refusés peut signaler un besoin '
                . 'de clarifier les règles de publication.',
            'categories' => 'Catégories les plus représentées parmi les annonces validées. Sert à prioriser le contenu du site, '
                . 'les campagnes marketing et le suivi des secteurs qui marchent le mieux.',
            'cities' => 'Villes les plus actives (annonces validées). Aide à cibler la communication locale et à détecter '
                . 'les zones sous-représentées.',
            'sellers' => 'Vendeurs avec le plus d’annonces validées et leur prix moyen. Permet d’identifier les power sellers '
                . 'et d’estimer le positionnement tarifaire du catalogue.',
            'contacts' => 'Messages reçus via le formulaire de contact du site : part « à traiter » vs déjà traités. '
                . 'Un stock ouvert élevé indique un retard côté support / administration.',
            'threads' => 'Conversations messagerie interne les plus actives (nombre de messages). Utile pour repérer les annonces '
                . 'qui génèrent le plus d’intérêt et vérifier qu’il n’y a pas de litiges ou spam.',
            'recent' => 'Dernières annonces créées (tous statuts). Donne un aperçu du flux récent à modérer ou à suivre.',
            'kpiUsers' => 'Nombre total de comptes inscrits. Les « vendeurs actifs » ont au moins une annonce validée.',
            'kpiApproved' => 'Annonces visibles sur le site après validation. Le détail indique celles encore en file de modération.',
            'kpiApprovalRate' => 'Part des annonces tranchées (validées + refusées) qui ont été acceptées. Hors brouillons et hors file en attente.',
            'kpiMessages' => 'Volume total de messages dans le chat interne, tous fils confondus.',
            'kpiContacts' => 'Total des demandes contact (ouvertes + traitées). Le détail rappelle le reste à traiter.',
            'kpiWhatsapp' => 'Comptes ayant renseigné un numéro WhatsApp sur leur profil (facilite le contact hors plateforme).',
            'kpiAvgPrice' => 'Moyenne des prix des annonces validées uniquement (en MAD).',
            'kpiCategories' => 'Nombre de catégories et de villes configurées dans le catalogue.',
            'kpiPriceRange' => 'Prix minimum et maximum observés sur les annonces validées.',
            'kpiTotal' => 'Somme de toutes les annonces, quel que soit le statut (y compris brouillons et refusées).',
        ];
    }

    private function countActiveSellers(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT seller.id)')
            ->from(Annonce::class, 'a')
            ->innerJoin('a.user', 'seller')
            ->andWhere('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countUsersWithWhatsapp(): int
    {
        return (int) $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.whatsappPhone IS NOT NULL')
            ->andWhere("u.whatsappPhone != ''")
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function avgApprovedPrice(): ?float
    {
        $val = $this->em->createQueryBuilder()
            ->select('AVG(a.price)')
            ->from(Annonce::class, 'a')
            ->andWhere('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->getQuery()
            ->getSingleScalarResult();

        return $val !== null ? round((float) $val, 0) : null;
    }

    /**
     * @return array{min: float|null, max: float|null}
     */
    private function approvedPriceRange(): array
    {
        $row = $this->em->createQueryBuilder()
            ->select('MIN(a.price) AS minPrice', 'MAX(a.price) AS maxPrice')
            ->from(Annonce::class, 'a')
            ->andWhere('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->getQuery()
            ->getSingleResult();

        return [
            'min' => isset($row['minPrice']) ? (float) $row['minPrice'] : null,
            'max' => isset($row['maxPrice']) ? (float) $row['maxPrice'] : null,
        ];
    }

    /**
     * @return array<string, int> key Y-m
     */
    private function countAnnoncesByMonth(\DateTimeImmutable $since): array
    {
        return $this->groupByMonth(
            'SELECT a.createdAt AS dt FROM App\Entity\Annonce a WHERE a.createdAt >= :since',
            $since
        );
    }

    /**
     * @return array<string, int>
     */
    private function countMessagesByMonth(\DateTimeImmutable $since): array
    {
        return $this->groupByMonth(
            'SELECT m.createdAt AS dt FROM App\Entity\Message m WHERE m.createdAt >= :since',
            $since
        );
    }

    /**
     * @return array<string, int>
     */
    private function countContactsByMonth(\DateTimeImmutable $since): array
    {
        return $this->groupByMonth(
            'SELECT c.createdAt AS dt FROM App\Entity\Contact c WHERE c.createdAt >= :since',
            $since
        );
    }

    /**
     * @return array<string, int>
     */
    private function countApprovalsByMonth(\DateTimeImmutable $since): array
    {
        $rows = $this->em->createQuery(
            'SELECT a.approvedAt AS dt FROM App\Entity\Annonce a WHERE a.approvedAt IS NOT NULL AND a.approvedAt >= :since'
        )->setParameter('since', $since)->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $dt = $row['dt'] ?? null;
            if (!$dt instanceof \DateTimeInterface) {
                continue;
            }
            $key = $dt->format('Y-m');
            $out[$key] = ($out[$key] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function groupByMonth(string $dql, \DateTimeImmutable $since): array
    {
        $rows = $this->em->createQuery($dql)->setParameter('since', $since)->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $dt = $row['dt'] ?? null;
            if (!$dt instanceof \DateTimeInterface) {
                continue;
            }
            $key = $dt->format('Y-m');
            $out[$key] = ($out[$key] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * @return list<string> Y-m keys
     */
    private function monthLabels(\DateTimeImmutable $since): array
    {
        $labels = [];
        $cursor = $since->modify('first day of this month');
        $end = new \DateTimeImmutable('first day of this month');
        while ($cursor <= $end) {
            $labels[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }

        return $labels;
    }

    /**
     * @param list<string> $labels
     * @param array<string, int> $series
     * @return list<int>
     */
    private function alignSeries(array $labels, array $series): array
    {
        $values = [];
        foreach ($labels as $label) {
            $values[] = $series[$label] ?? 0;
        }

        return $values;
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function annoncesByCategory(int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('c.name AS name', 'COUNT(a.id) AS total')
            ->from(Annonce::class, 'a')
            ->innerJoin('a.category', 'c')
            ->andWhere('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->groupBy('c.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return [
            'labels' => array_map(static fn (array $r): string => (string) $r['name'], $rows),
            'values' => array_map(static fn (array $r): int => (int) $r['total'], $rows),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function annoncesByCity(int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('city.name AS name', 'COUNT(a.id) AS total')
            ->from(Annonce::class, 'a')
            ->innerJoin('a.city', 'city')
            ->andWhere('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->groupBy('city.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return [
            'labels' => array_map(static fn (array $r): string => (string) $r['name'], $rows),
            'values' => array_map(static fn (array $r): int => (int) $r['total'], $rows),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function topSellers(int $limit): array
    {
        $rows = $this->topSellersDetailed($limit);

        return [
            'labels' => array_map(static fn (array $r): string => $r['name'], $rows),
            'values' => array_map(static fn (array $r): int => $r['total'], $rows),
        ];
    }

    /**
     * @return list<array{id: int, name: string, email: string, total: int, avgPrice: float|null}>
     */
    private function topSellersDetailed(int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('seller.id AS id', 'seller.firstName AS firstName', 'seller.lastName AS lastName', 'seller.email AS email', 'COUNT(a.id) AS total', 'AVG(a.price) AS avgPrice')
            ->from(Annonce::class, 'a')
            ->innerJoin('a.user', 'seller')
            ->andWhere('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->groupBy('seller.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'name' => trim(($r['firstName'] ?? '') . ' ' . ($r['lastName'] ?? '')),
                'email' => (string) ($r['email'] ?? ''),
                'total' => (int) $r['total'],
                'avgPrice' => isset($r['avgPrice']) ? round((float) $r['avgPrice'], 0) : null,
            ];
        }, $rows);
    }

    /**
     * @return list<array{id: int, title: string, status: string, price: float, seller: string, createdAt: string}>
     */
    private function recentAnnonces(int $limit): array
    {
        $annonces = $this->annonceRepository->createQueryBuilder('a')
            ->innerJoin('a.user', 'seller')->addSelect('seller')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($annonces as $annonce) {
            if (!$annonce instanceof Annonce) {
                continue;
            }
            $seller = $annonce->getUser();
            $out[] = [
                'id' => (int) $annonce->getId(),
                'title' => $annonce->getTitle(),
                'status' => $annonce->getStatus(),
                'price' => $annonce->getPrice(),
                'seller' => $seller ? trim($seller->getFirstName() . ' ' . $seller->getLastName()) : '—',
                'createdAt' => $annonce->getCreatedAt()->format('d/m/Y'),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: int, annonce: string, messages: int, buyer: string, seller: string}>
     */
    private function busiestThreads(int $limit): array
    {
        $threads = $this->threadRepository->findAllForAdminOrderedByNewest(0);
        $ranked = [];
        foreach ($threads as $thread) {
            $count = $thread->getMessagesAsThread()->count();
            $ranked[] = [
                'id' => (int) $thread->getId(),
                'annonce' => $thread->getAnnonce()?->getTitle() ?? 'Annonce supprimée',
                'messages' => $count,
                'buyer' => $thread->getBuyer()
                    ? trim($thread->getBuyer()->getFirstName() . ' ' . $thread->getBuyer()->getLastName())
                    : '—',
                'seller' => $thread->getSeller()
                    ? trim($thread->getSeller()->getFirstName() . ' ' . $thread->getSeller()->getLastName())
                    : '—',
            ];
        }
        usort($ranked, static fn (array $a, array $b): int => $b['messages'] <=> $a['messages']);

        return array_slice($ranked, 0, $limit);
    }

    /**
     * @return list<string>
     */
    private function buildInsights(
        int $users,
        int $sellers,
        int $approved,
        int $pending,
        int $rejected,
        int $messages,
        int $threads,
        int $contactsOpen,
        int $contactsDone,
        ?float $avgPrice,
        string $periodLabel,
    ): array {
        $insights = [];

        $insights[] = sprintf(
            'Période analysée : %s. Les tendances graphiques portent sur cet intervalle ; les totaux (utilisateurs, stock d’annonces) sont calculés sur l’ensemble de la base.',
            $periodLabel
        );

        if ($pending > 0) {
            $insights[] = sprintf(
                'Modération : %d annonce(s) encore en attente. Priorisez la validation pour éviter un catalogue figé et des vendeurs insatisfaits.',
                $pending
            );
        } else {
            $insights[] = 'Modération : aucune annonce en attente — la file est à jour. Vous pouvez vous concentrer sur le suivi qualité et le support.';
        }

        if ($rejected > 0) {
            $insights[] = sprintf(
                'Qualité : %d annonce(s) refusée(s) au total. Surveillez les motifs récurrents (photos, prix, catégorie) pour mieux guider les vendeurs.',
                $rejected
            );
        }

        if ($users > 0) {
            $sellerRate = round(100 * $sellers / $users, 1);
            $insights[] = sprintf(
                'Communauté : %s %% des comptes sont des vendeurs actifs (%d sur %d). Un taux faible peut justifier des campagnes d’incitation à publier.',
                $sellerRate,
                $sellers,
                $users
            );
        }

        if ($approved > 0 && $avgPrice !== null) {
            $insights[] = sprintf(
                'Catalogue : %d annonce(s) validée(s) en ligne, pour un prix moyen de %s MAD. Cela donne une idée du panier type sur SoukExpat.',
                $approved,
                number_format($avgPrice, 0, ',', ' ')
            );
        }

        if ($messages > 0) {
            $avgPerThread = $threads > 0 ? round($messages / $threads, 1) : 0;
            $insights[] = sprintf(
                'Engagement chat : %d message(s) dans %d conversation(s) (≈ %s msg / conversation). Un volume élevé montre que la messagerie est utilisée.',
                $messages,
                $threads,
                $avgPerThread
            );
        } else {
            $insights[] = 'Engagement chat : pas encore de messages. Vérifiez la visibilité du bouton « Contacter » et l’expérience mobile.';
        }

        $contactsTotal = $contactsOpen + $contactsDone;
        if ($contactsTotal > 0) {
            $insights[] = sprintf(
                'Support contact : %d demande(s) au total, dont %d encore ouverte(s) et %d déjà traitée(s). Traitez d’abord les ouvertes pour garder un bon délai de réponse.',
                $contactsTotal,
                $contactsOpen,
                $contactsDone
            );
        }

        return $insights;
    }
}
