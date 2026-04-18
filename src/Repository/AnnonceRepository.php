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
    public function createApprovedListingQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'seller')->addSelect('seller')
            ->innerJoin('a.city', 'city')->addSelect('city')
            ->innerJoin('a.category', 'category')->addSelect('category')
            ->where('a.status = :status')
            ->setParameter('status', Annonce::STATUS_APPROVED)
            ->orderBy('a.createdAt', 'DESC');
    }

    /**
     * @return list<Annonce>
     */
    public function findRecentApproved(int $limit = 4): array
    {
        return $this->createApprovedListingQueryBuilder()
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste approuvée avec filtres optionnels (texte, catégorie, ville).
     */
    public function createApprovedSearchQueryBuilder(?string $search, ?int $categoryId, ?int $cityId): QueryBuilder
    {
        $qb = $this->createApprovedListingQueryBuilder();

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
                    $qb->expr()->like('LOWER(a.description)', ':searchLike'),
                    $qb->expr()->like('LOWER(a.phone)', ':searchLike')
                )
            )->setParameter('searchLike', $like);
        }

        return $qb;
    }

    /**
     * Annonces approuvées dont la date de validation est antérieure à la limite (expiration des 30 jours en ligne).
     *
     * @return list<Annonce>
     */
    public function findApprovedExpiredBefore(\DateTimeImmutable $exclusiveUpperBoundOfApprovedAt): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.status = :approved')
            ->andWhere('a.approvedAt IS NOT NULL')
            ->andWhere('a.approvedAt < :limit')
            ->setParameter('approved', Annonce::STATUS_APPROVED)
            ->setParameter('limit', $exclusiveUpperBoundOfApprovedAt)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Annonce[] Returns an array of Annonce objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Annonce
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
