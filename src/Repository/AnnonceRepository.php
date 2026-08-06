<?php

namespace App\Repository;

use App\Entity\Annonce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Annonce>
 */
class AnnonceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Annonce::class);
    }

    /**
     * Annonces approuvées dont l'auteur, la ville et la catégorie existent encore.
     * Un INNER JOIN sur user évite les annonces orphelines (user supprimé hors contrainte FK).
     */
    public function createApprovedListingQueryBuilder(bool $randomOrder = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'seller')->addSelect('seller')
            ->innerJoin('a.city', 'city')->addSelect('city')
            ->innerJoin('a.category', 'category')->addSelect('category')
            ->where('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED);

        if ($randomOrder) {
            // Pseudo-aléatoire sans RAND() MySQL (plus léger sur de gros volumes)
            $seed = random_int(1, 999_983);
            $qb->addSelect('ABS(a.id * :randSeed) AS HIDDEN rand_ord')
                ->setParameter('randSeed', $seed)
                ->orderBy('rand_ord', 'ASC')
                ->addOrderBy('a.id', 'DESC');
        } else {
            $qb->orderBy('a.createdAt', 'DESC')
                ->addOrderBy('a.id', 'DESC');
        }

        return $qb;
    }

    /**
     * Précharge les images des annonces déjà hydratées (évite le N+1 en liste).
     *
     * @param list<Annonce> $annonces
     */
    public function prefetchImages(array $annonces): void
    {
        if ($annonces === []) {
            return;
        }

        $ids = [];
        foreach ($annonces as $annonce) {
            $id = $annonce->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        $this->createQueryBuilder('a')
            ->leftJoin('a.annonceImages', 'img')->addSelect('img')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Annonce>
     */
    public function findRecentApproved(int $limit = 4): array
    {
        return $this->createApprovedListingQueryBuilder(false)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste approuvée avec filtres optionnels (texte, catégorie, ville).
     */
    public function createApprovedSearchQueryBuilder(
        ?string $search,
        ?int $categoryId,
        ?int $cityId,
        bool $randomOrder = true,
    ): QueryBuilder {
        $qb = $this->createApprovedListingQueryBuilder($randomOrder);

        if ($categoryId !== null && $categoryId > 0) {
            $qb->andWhere('category.id = :filterCategoryId')
                ->setParameter('filterCategoryId', $categoryId);
        }

        if ($cityId !== null && $cityId > 0) {
            $qb->andWhere('city.id = :filterCityId')
                ->setParameter('filterCityId', $cityId);
        }

        $term = $search !== null ? trim($search) : '';
        if ($term !== '') {
            $like = '%' . mb_strtolower($term, 'UTF-8') . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(a.title)', ':searchLike'),
                    $qb->expr()->like('LOWER(a.description)', ':searchLike')
                )
            )->setParameter('searchLike', $like);
        }

        return $qb;
    }

    /**
     * Annonces approuvées d'un vendeur (ordre aléatoire par défaut).
     */
    public function createApprovedByUserQueryBuilder(int $userId, bool $randomOrder = true): QueryBuilder
    {
        return $this->createApprovedListingQueryBuilder($randomOrder)
            ->andWhere('seller.id = :sellerId')
            ->setParameter('sellerId', $userId);
    }

    /**
     * Nombre d'annonces approuvées pour un utilisateur.
     */
    public function countApprovedByUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->innerJoin('a.user', 'seller')
            ->where('a.status = :status')
            ->andWhere('seller.id = :sellerId')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->setParameter('sellerId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Stats publiques d’un vendeur (annonces approuvées).
     *
     * @return array{
     *     count: int,
     *     minPrice: float|null,
     *     maxPrice: float|null,
     *     categories: list<array{id: int, name: string, slug: string, total: int}>,
     *     cities: list<array{name: string, total: int}>
     * }
     */
    public function getSellerPublicStats(int $userId): array
    {
        $agg = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS cnt', 'MIN(a.price) AS minPrice', 'MAX(a.price) AS maxPrice')
            ->innerJoin('a.user', 'seller')
            ->where('a.status = :status')
            ->andWhere('seller.id = :sellerId')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->setParameter('sellerId', $userId)
            ->getQuery()
            ->getSingleResult();

        $categories = $this->createQueryBuilder('a')
            ->select('category.id AS id', 'category.name AS name', 'category.slug AS slug', 'COUNT(a.id) AS total')
            ->innerJoin('a.user', 'seller')
            ->innerJoin('a.category', 'category')
            ->where('a.status = :status')
            ->andWhere('seller.id = :sellerId')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->setParameter('sellerId', $userId)
            ->groupBy('category.id')
            ->orderBy('total', 'DESC')
            ->addOrderBy('category.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $cities = $this->createQueryBuilder('a')
            ->select('city.name AS name', 'COUNT(a.id) AS total')
            ->innerJoin('a.user', 'seller')
            ->innerJoin('a.city', 'city')
            ->where('a.status = :status')
            ->andWhere('seller.id = :sellerId')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->setParameter('sellerId', $userId)
            ->groupBy('city.id')
            ->orderBy('total', 'DESC')
            ->addOrderBy('city.name', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getArrayResult();

        return [
            'count' => (int) ($agg['cnt'] ?? 0),
            'minPrice' => isset($agg['minPrice']) ? (float) $agg['minPrice'] : null,
            'maxPrice' => isset($agg['maxPrice']) ? (float) $agg['maxPrice'] : null,
            'categories' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'total' => (int) $row['total'],
            ], $categories),
            'cities' => array_map(static fn (array $row): array => [
                'name' => (string) $row['name'],
                'total' => (int) $row['total'],
            ], $cities),
        ];
    }

    /**
     * Annonces validées antérieures à une date (nettoyage admin).
     * Critère : approvedAt, sinon createdAt.
     *
     * @return list<Annonce>
     */
    public function findApprovedOlderThan(\DateTimeImmutable $cutoff): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'seller')->addSelect('seller')
            ->innerJoin('a.category', 'category')->addSelect('category')
            ->andWhere('a.status = :status')
            ->andWhere('(a.approvedAt IS NOT NULL AND a.approvedAt < :cutoff) OR (a.approvedAt IS NULL AND a.createdAt < :cutoff)')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('a.approvedAt', 'ASC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countApprovedOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.status = :status')
            ->andWhere('(a.approvedAt IS NOT NULL AND a.approvedAt < :cutoff) OR (a.approvedAt IS NULL AND a.createdAt < :cutoff)')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $ids
     * @return list<Annonce>
     */
    public function findApprovedByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->andWhere('a.status = :status')
            ->setParameter('ids', $ids)
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->getQuery()
            ->getResult();
    }
}
