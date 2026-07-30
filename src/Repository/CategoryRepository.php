<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findPopular(int $limit = 6): array
    {
        return $this->createQueryBuilder('c')
            // Optionnel : trier par celles qui ont le plus d'annonces
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Category>
     */
    public function findAllOrderedByName(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    /**
     * Catégories avec le nombre d'annonces approuvées (pour l’accueil).
     *
     * @return list<array{category: Category, total: int}>
     */
    public function findWithApprovedCounts(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c AS category', 'COUNT(a.id) AS total')
            ->leftJoin('c.annonces', 'a', 'WITH', 'a.status = :approved')
            ->setParameter('approved', \App\Entity\Annonce::STATUS_APPROVED)
            ->groupBy('c.id')
            ->orderBy('total', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'category' => $row['category'],
                'total' => (int) $row['total'],
            ];
        }

        return $out;
    }

    //    /**
    //     * @return Category[] Returns an array of Category objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Category
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
