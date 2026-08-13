<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    #[Route('/notification/read/{id}', name: 'app_notification_read', methods: ['POST'])]
    public function read(Notification $notification, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $owner = $notification->getUser();
        if (!$owner || $owner->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas lire cette notification.');
        }

        if (!$this->isCsrfTokenValid('notif_read'.$notification->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_home');
        }

        $notification->setIsRead(true);
        $em->flush();

        return $this->redirectToRoute('app_home');
    }

    #[Route('/notification/read-all', name: 'app_notification_read_all', methods: ['POST'])]
    public function readAll(Request $request, EntityManagerInterface $em, NotificationRepository $notifRepo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('notif_read_all', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_home');
        }

        $notifications = $notifRepo->findBy([
            'user' => $user,
            'isRead' => false,
        ]);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $em->flush();

        return $this->redirectToRoute('app_home');
    }

    #[Route('/notification/delete-all', name: 'app_notification_delete_all', methods: ['POST'])]
    public function deleteAll(Request $request, EntityManagerInterface $em, NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('notif_delete_all', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_home');
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
