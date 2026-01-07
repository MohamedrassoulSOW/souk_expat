<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository
    ) {}

    /**
     * Envoyer à un utilisateur précis (ex: réponse contact)
     */
    public function notifyUser(User $user, string $title, string $message): void
    {
        $notification = new Notification();
        $notification->setUser($user)
                    ->setTitle($title)
                    ->setMessage($message)
                    ->setIsRead(false)
                    ->setCreatedAt(new \DateTimeImmutable()); // AJOUTÉ : Indispensable pour la DB

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Envoyer à TOUT LE MONDE (Information générale)
     */
    public function notifyAll(string $title, string $message): void
    {
        $users = $this->userRepository->findAll();
        
        foreach ($users as $user) {
            $notification = new Notification();
            $notification->setUser($user)
                         ->setTitle($title)
                         ->setMessage($message)
                         ->setIsRead(false) // AJOUTÉ : Sinon erreur SQL
                         ->setCreatedAt(new \DateTimeImmutable());
                         
            $this->em->persist($notification);
        }
        
        // On flush une seule fois après la boucle pour optimiser les performances
        $this->em->flush();
    }

    public function cleanOldNotifications(): void
    {
        // Date d'il y a 30 jours
        $date = new \DateTimeImmutable('-30 days');

        $oldNotifications = $this->em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->where('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        foreach ($oldNotifications as $notif) {
            $this->em->remove($notif);
        }
        
        $this->em->flush();
    }
}