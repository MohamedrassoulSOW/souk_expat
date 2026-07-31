<?php

namespace App\Repository;

use App\Entity\Thread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Thread>
 */
class ThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Thread::class);
    }

    public function findByUser($user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.buyer = :user OR t.seller = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste complète des fils (admin), les plus récents en premier.
     *
     * @return list<Thread>
     */
    public function findAllForAdminOrderedByNewest(int $maxResults = 0): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.annonce', 'a')->addSelect('a')
            ->leftJoin('t.buyer', 'b')->addSelect('b')
            ->leftJoin('t.seller', 's')->addSelect('s')
            ->orderBy('t.id', 'DESC');

        if ($maxResults > 0) {
            $qb->setMaxResults($maxResults);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Conversations sans aucun message restant.
     *
     * @return list<Thread>
     */
    public function findEmptyThreads(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.messagesAsThread', 'm')
            ->andWhere('m.id IS NULL')
            ->getQuery()
            ->getResult();
    }
}
