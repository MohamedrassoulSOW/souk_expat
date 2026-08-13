<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Annonce;
use App\Repository\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Agrégats du rapport annonces (dashboard admin) — conçu pour être étendu.
 */
final class AnnonceReportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AnnonceRepository $annonceRepository,
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
            $periodLabel = $value . ' jour' . ($value > 1 ? 's' : '');
        } else {
            $value = max(1, min(60, $value));
            $since = new \DateTimeImmutable(sprintf('first day of -%d months 00:00:00', $value - 1));
            $periodLabel = $value . ' mois';
        }

        $pending = $this->annonceRepository->count(['status' => Annonce::STATUS_PENDING]);
        $approved = $this->annonceRepository->count(['status' => Annonce::STATUS_APPROVED]);
        $rejected = $this->annonceRepository->count(['status' => Annonce::STATUS_REJECTED]);
        $draft = $this->annonceRepository->count(['status' => Annonce::STATUS_DRAFT]);
        $total = $pending + $approved + $rejected + $draft;

        $withPhotos = $this->countWithPhotos();
        $withoutPhotos = max(0, $total - $withPhotos);
        $avgPrice = $this->avgPrice(Annonce::STATUS_APPROVED);
        $priceRange = $this->priceRange(Annonce::STATUS_APPROVED);
        $createdInPeriod = $this->countCreatedSince($since);
        $avgModerationHours = $this->avgModerationHours($since);

        $monthLabels = $this->monthLabels($since);

        return [
            'generatedAt' => new \DateTimeImmutable(),
            'periodValue' => $value,
            'periodUnit' => $unit,
            'periodLabel' => $periodLabel,
            'since' => $since,
            'kpis' => [
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'draft' => $draft,
                'createdInPeriod' => $createdInPeriod,
                'withPhotos' => $withPhotos,
                'withoutPhotos' => $withoutPhotos,
                'avgPrice' => $avgPrice,
                'minPrice' => $priceRange['min'],
                'maxPrice' => $priceRange['max'],
                'avgModerationHours' => $avgModerationHours,
                'approvalRate' => ($approved + $rejected) > 0
                    ? round(100 * $approved / ($approved + $rejected), 1)
                    : null,
            ],
            'charts' => [
                'monthLabels' => $monthLabels,
                'createdByMonth' => $this->alignSeries($monthLabels, $this->countByMonth('a.createdAt', $since)),
                'approvedByMonth' => $this->alignSeries($monthLabels, $this->countByMonth('a.approvedAt', $since, true)),
                'rejectedByMonth' => $this->alignSeries($monthLabels, $this->countRejectedByMonth($since)),
                'statusLabels' => ['Validées', 'En attente', 'Refusées', 'Brouillons'],
                'statusValues' => [$approved, $pending, $rejected, $draft],
                'categories' => $this->groupByRelation('category', 12),
                'cities' => $this->groupByRelation('city', 12),
                'priceBuckets' => $this->priceBuckets(),
                'photos' => [
                    'labels' => ['Avec photo', 'Sans photo'],
                    'values' => [$withPhotos, $withoutPhotos],
                ],
            ],
            'tables' => [
                'categoriesBreakdown' => $this->categoriesBreakdown(15),
                'citiesBreakdown' => $this->citiesBreakdown(12),
                'recent' => $this->recentAnnonces(12),
                'sellers' => $this->sellersBreakdown(15),
            ],
            'explanations' => $this->buildExplanations($periodLabel, $since),
            'insights' => $this->buildInsights($total, $pending, $approved, $rejected, $draft, $createdInPeriod, $withPhotos, $avgPrice, $avgModerationHours, $periodLabel),
        ];
    }

    /**
     * Rapport enrichi pour export Word / Excel / PowerPoint.
     *
     * @param 'months'|'days' $unit
     * @return array<string, mixed>
     */
    public function buildExportReport(int $value = 12, string $unit = 'months'): array
    {
        $report = $this->buildReport($value, $unit);
        $report['tables']['categoriesBreakdown'] = $this->categoriesBreakdown(50);
        $report['tables']['citiesBreakdown'] = $this->citiesBreakdown(30);
        $report['tables']['sellers'] = $this->sellersBreakdown(40);
        $report['tables']['recent'] = $this->recentAnnonces(200);
        $report['tables']['pending'] = $this->annoncesByStatus(Annonce::STATUS_PENDING, 100);
        $report['tables']['approved'] = $this->annoncesByStatus(Annonce::STATUS_APPROVED, 100);
        $report['tables']['rejected'] = $this->annoncesByStatus(Annonce::STATUS_REJECTED, 100);

        return $report;
    }

    public function dashboardTotals(): array
    {
        $pending = $this->annonceRepository->count(['status' => Annonce::STATUS_PENDING]);
        $approved = $this->annonceRepository->count(['status' => Annonce::STATUS_APPROVED]);
        $rejected = $this->annonceRepository->count(['status' => Annonce::STATUS_REJECTED]);
        $draft = $this->annonceRepository->count(['status' => Annonce::STATUS_DRAFT]);

        return [
            'total' => $pending + $approved + $rejected + $draft,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'draft' => $draft,
        ];
    }

    private function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Annonce::class, 'a')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countWithPhotos(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT a.id)')
            ->from(Annonce::class, 'a')
            ->innerJoin('a.annonceImages', 'img')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function avgPrice(string $status): ?float
    {
        $val = $this->em->createQueryBuilder()
            ->select('AVG(a.price)')
            ->from(Annonce::class, 'a')
            ->andWhere('a.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return $val !== null ? round((float) $val, 0) : null;
    }

    /**
     * @return array{min: float|null, max: float|null}
     */
    private function priceRange(string $status): array
    {
        $row = $this->em->createQueryBuilder()
            ->select('MIN(a.price) AS minPrice', 'MAX(a.price) AS maxPrice')
            ->from(Annonce::class, 'a')
            ->andWhere('a.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleResult();

        return [
            'min' => isset($row['minPrice']) ? (float) $row['minPrice'] : null,
            'max' => isset($row['maxPrice']) ? (float) $row['maxPrice'] : null,
        ];
    }

    private function avgModerationHours(\DateTimeImmutable $since): ?float
    {
        $rows = $this->em->createQuery(
            'SELECT a.createdAt AS created, a.approvedAt AS approved
             FROM App\Entity\Annonce a
             WHERE a.approvedAt IS NOT NULL AND a.approvedAt >= :since'
        )->setParameter('since', $since)->getArrayResult();

        $hours = [];
        foreach ($rows as $row) {
            $created = $row['created'] ?? null;
            $approved = $row['approved'] ?? null;
            if (!$created instanceof \DateTimeInterface || !$approved instanceof \DateTimeInterface) {
                continue;
            }
            $diff = ($approved->getTimestamp() - $created->getTimestamp()) / 3600;
            if ($diff >= 0) {
                $hours[] = $diff;
            }
        }

        if ($hours === []) {
            return null;
        }

        return round(array_sum($hours) / count($hours), 1);
    }

    /**
     * @return array<string, int>
     */
    private function countByMonth(string $field, \DateTimeImmutable $since, bool $notNull = false): array
    {
        $dql = sprintf(
            'SELECT %s AS dt FROM App\Entity\Annonce a WHERE %s >= :since%s',
            $field,
            $field,
            $notNull ? sprintf(' AND %s IS NOT NULL', $field) : ''
        );

        return $this->groupDates($this->em->createQuery($dql)->setParameter('since', $since)->getArrayResult());
    }

    /**
     * @return array<string, int>
     */
    private function countRejectedByMonth(\DateTimeImmutable $since): array
    {
        $rows = $this->em->createQuery(
            'SELECT a.updatedAt AS dt FROM App\Entity\Annonce a
             WHERE a.status = :status AND a.updatedAt IS NOT NULL AND a.updatedAt >= :since'
        )
            ->setParameter('status', Annonce::STATUS_REJECTED)
            ->setParameter('since', $since)
            ->getArrayResult();

        return $this->groupDates($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function groupDates(array $rows): array
    {
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
     * @return list<string>
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
    private function groupByRelation(string $relation, int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('rel.name AS name', 'COUNT(a.id) AS total')
            ->from(Annonce::class, 'a')
            ->innerJoin('a.' . $relation, 'rel')
            ->andWhere('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->groupBy('rel.id')
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
    private function priceBuckets(): array
    {
        $rows = $this->em->createQuery(
            'SELECT a.price FROM App\Entity\Annonce a WHERE a.status = :status'
        )->setParameter('status', Annonce::STATUS_APPROVED)->getArrayResult();

        $buckets = [
            '< 500' => 0,
            '500 – 1 999' => 0,
            '2 000 – 4 999' => 0,
            '5 000 – 19 999' => 0,
            '20 000+' => 0,
        ];

        foreach ($rows as $row) {
            $price = (float) ($row['price'] ?? 0);
            if ($price < 500) {
                ++$buckets['< 500'];
            } elseif ($price < 2000) {
                ++$buckets['500 – 1 999'];
            } elseif ($price < 5000) {
                ++$buckets['2 000 – 4 999'];
            } elseif ($price < 20000) {
                ++$buckets['5 000 – 19 999'];
            } else {
                ++$buckets['20 000+'];
            }
        }

        return [
            'labels' => array_keys($buckets),
            'values' => array_values($buckets),
        ];
    }

    /**
     * @return list<array{name: string, total: int, approved: int, pending: int, rejected: int}>
     */
    private function categoriesBreakdown(int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select(
                'c.name AS name',
                'COUNT(a.id) AS total',
                'SUM(CASE WHEN a.status = :approved THEN 1 ELSE 0 END) AS approved',
                'SUM(CASE WHEN a.status = :pending THEN 1 ELSE 0 END) AS pending',
                'SUM(CASE WHEN a.status = :rejected THEN 1 ELSE 0 END) AS rejected'
            )
            ->from(Annonce::class, 'a')
            ->innerJoin('a.category', 'c')
            ->setParameter('approved', Annonce::STATUS_APPROVED)
            ->setParameter('pending', Annonce::STATUS_PENDING)
            ->setParameter('rejected', Annonce::STATUS_REJECTED)
            ->groupBy('c.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            'name' => (string) $r['name'],
            'total' => (int) $r['total'],
            'approved' => (int) $r['approved'],
            'pending' => (int) $r['pending'],
            'rejected' => (int) $r['rejected'],
        ], $rows);
    }

    /**
     * @return list<array{name: string, total: int, approved: int}>
     */
    private function citiesBreakdown(int $limit): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select(
                'city.name AS name',
                'COUNT(a.id) AS total',
                'SUM(CASE WHEN a.status = :approved THEN 1 ELSE 0 END) AS approved'
            )
            ->from(Annonce::class, 'a')
            ->innerJoin('a.city', 'city')
            ->setParameter('approved', Annonce::STATUS_APPROVED)
            ->groupBy('city.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            'name' => (string) $r['name'],
            'total' => (int) $r['total'],
            'approved' => (int) $r['approved'],
        ], $rows);
    }

    /**
     * @return list<array{id: int, title: string, status: string, price: float, city: string, category: string, createdAt: string}>
     */
    private function recentAnnonces(int $limit): array
    {
        $annonces = $this->annonceRepository->createQueryBuilder('a')
            ->innerJoin('a.city', 'city')->addSelect('city')
            ->innerJoin('a.category', 'cat')->addSelect('cat')
            ->innerJoin('a.user', 'seller')->addSelect('seller')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->mapAnnoncesForExport($annonces);
    }

    /**
     * @return list<array{id: int, name: string, email: string, total: int, approved: int, pending: int, rejected: int, avgPrice: float|null}>
     */
    private function sellersBreakdown(int $limit): array
    {
        $stats = $this->annonceRepository->countStatsIndexedByUserId();
        if ($stats === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('u.id AS id', 'u.firstName AS firstName', 'u.lastName AS lastName', 'u.email AS email')
            ->from(\App\Entity\User::class, 'u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', array_keys($stats))
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $s = $stats[$id] ?? AnnonceRepository::emptyUserListingStats();
            if ($s['total'] === 0) {
                continue;
            }
            $avg = $this->em->createQueryBuilder()
                ->select('AVG(a.price)')
                ->from(Annonce::class, 'a')
                ->andWhere('a.user = :uid')
                ->andWhere('a.status = :status')
                ->setParameter('uid', $id)
                ->setParameter('status', Annonce::STATUS_APPROVED)
                ->getQuery()
                ->getSingleScalarResult();

            $out[] = [
                'id' => $id,
                'name' => trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
                'email' => (string) ($row['email'] ?? ''),
                'total' => $s['total'],
                'approved' => $s['approved'],
                'pending' => $s['pending'],
                'rejected' => $s['rejected'],
                'avgPrice' => $avg !== null ? round((float) $avg, 0) : null,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return array_slice($out, 0, $limit);
    }

    /**
     * @return list<array{id: int, title: string, status: string, price: float, city: string, category: string, seller: string, sellerEmail: string, createdAt: string, hasPhoto: string}>
     */
    private function annoncesByStatus(string $status, int $limit): array
    {
        $annonces = $this->annonceRepository->createQueryBuilder('a')
            ->innerJoin('a.city', 'city')->addSelect('city')
            ->innerJoin('a.category', 'cat')->addSelect('cat')
            ->innerJoin('a.user', 'seller')->addSelect('seller')
            ->andWhere('a.status = :status')
            ->setParameter('status', $status)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->mapAnnoncesForExport($annonces);
    }

    /**
     * @param iterable<Annonce> $annonces
     * @return list<array{id: int, title: string, status: string, price: float, city: string, category: string, seller: string, sellerEmail: string, createdAt: string, hasPhoto: string}>
     */
    private function mapAnnoncesForExport(iterable $annonces): array
    {
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
                'city' => $annonce->getCity()->getName(),
                'category' => $annonce->getCategory()->getName(),
                'seller' => $seller ? trim($seller->getFirstName() . ' ' . $seller->getLastName()) : '—',
                'sellerEmail' => $seller ? (string) $seller->getEmail() : '',
                'createdAt' => $annonce->getCreatedAt()->format('d/m/Y'),
                'hasPhoto' => $annonce->getAnnonceImages()->count() > 0 ? 'Oui' : 'Non',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function buildExplanations(string $periodLabel, \DateTimeImmutable $since): array
    {
        return [
            'intro' => sprintf(
                'Ce rapport détaille le catalogue d’annonces SoukExpat sur %s (depuis le %s). '
                . 'Les indicateurs « stock » reflètent l’état actuel ; les tendances mensuelles couvrent la période choisie.',
                $periodLabel,
                $since->format('d/m/Y')
            ),
            'howToRead' => 'Commencez par les points clés, puis les KPI, les diagrammes et les tableaux détaillés '
                . '(catégories, villes, vendeurs, listes par statut). Exportez en Word, Excel ou PowerPoint pour partager ou archiver.',
            'kpis' => 'Vue synthétique : volume total, file de modération, prix moyen, qualité visuelle (photos) et délai de validation.',
            'trends' => 'Évolution mensuelle des créations, validations et refus — utile pour anticiper la charge modération.',
            'status' => 'Répartition actuelle par statut (validées, en attente, refusées, brouillons).',
            'categories' => 'Où se concentrent les annonces, tous statuts confondus, avec détail par statut.',
            'cities' => 'Répartition géographique du catalogue.',
            'sellers' => 'Vendeurs les plus actifs avec le détail publié / validé / en attente / refusé.',
            'photos' => 'Part des annonces avec au moins une photo — indicateur qualité du catalogue.',
            'prices' => 'Distribution des prix des annonces validées en MAD.',
        ];
    }

    /**
     * @return list<string>
     */
    private function buildInsights(
        int $total,
        int $pending,
        int $approved,
        int $rejected,
        int $draft,
        int $createdInPeriod,
        int $withPhotos,
        ?float $avgPrice,
        ?float $avgModerationHours,
        string $periodLabel,
    ): array {
        $insights = [];
        $insights[] = sprintf('Période analysée : %s — %d annonce(s) créée(s) sur cette période.', $periodLabel, $createdInPeriod);

        if ($pending > 0) {
            $insights[] = sprintf('%d annonce(s) en attente de modération — priorisez la validation.', $pending);
        } else {
            $insights[] = 'Aucune annonce en attente : la file de modération est à jour.';
        }

        if ($total > 0) {
            $photoRate = round(100 * $withPhotos / $total, 1);
            $insights[] = sprintf('%s %% des annonces ont au moins une photo (%d / %d).', $photoRate, $withPhotos, $total);
        }

        if ($approved > 0 && $avgPrice !== null) {
            $insights[] = sprintf('%d annonce(s) en ligne, prix moyen %s MAD.', $approved, number_format($avgPrice, 0, ',', ' '));
        }

        if ($avgModerationHours !== null) {
            $insights[] = sprintf('Délai moyen de validation sur la période : %s heure(s).', $avgModerationHours);
        }

        if ($rejected > 0) {
            $insights[] = sprintf('%d annonce(s) refusée(s) au total — surveillez les motifs récurrents.', $rejected);
        }

        if ($draft > 0) {
            $insights[] = sprintf('%d brouillon(s) non publiés.', $draft);
        }

        return $insights;
    }
}
