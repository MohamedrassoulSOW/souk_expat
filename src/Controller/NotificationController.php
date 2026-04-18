<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    /**
     * Marquer une notification comme lue
     */
    #[Route('/notification/read/{id}', name: 'app_notification_read')]
    public function read(Notification $notification, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $owner = $notification->getUser();
        if (!$owner || $owner->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas lire cette notification.');
        }

        $notification->setIsRead(true);
        $em->flush();

        // Redirige vers la page d'accueil ou la page précédente
        return $this->redirectToRoute('app_home');
    }

    /**
     * Tout marquer comme lu
     */
    #[Route('/notification/read-all', name: 'app_notification_read_all')]
    public function readAll(EntityManagerInterface $em, NotificationRepository $notifRepo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $notifications = $notifRepo->findBy([
            'user' => $user,
            'isRead' => false
        ]);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $em->flush();

        return $this->redirectToRoute('app_home');
    }

    #[Route('/notification/delete-all', name: 'app_notification_delete_all')]
    public function deleteAll(EntityManagerInterface $em, NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $notifications = $notificationRepository->findBy(['user' => $user]);

        foreach ($notifications as $notification) {
            $em->remove($notification);
        }
        
        $em->flush();

        $this->addFlash('success', 'Toutes les notifications ont été supprimées.');
        return $this->redirectToRoute('app_home');
    }

}