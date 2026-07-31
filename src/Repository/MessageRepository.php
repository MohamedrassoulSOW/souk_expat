<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Compte les messages non lus pour un utilisateur donné.
     * On compte les messages qui sont dans ses threads mais qu'il n'a pas envoyé lui-même.
     */
    public function countUnreadMessages($user): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.thread', 't')
            ->where('t.buyer = :user OR t.seller = :user')
            ->andWhere('m.sender != :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * @return list<Message>
     */
    public function findCreatedBefore(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.createdAt < :before')
            ->setParameter('before', $before)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}