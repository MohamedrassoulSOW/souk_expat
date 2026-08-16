<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class NotificationController extends AbstractController
{
    /**
     * Liste légère pour le dropdown navbar (chargée au premier clic).
     */
    #[Route('/notification/preview', name: 'app_notification_preview', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function preview(NotificationRepository $notifRepo, CsrfTokenManagerInterface $csrf): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $items = [];
        foreach ($notifRepo->findLatestForUser($user, 8) as $notification) {
            $id = (int) $notification->getId();
            $items[] = [
                'id' => $id,
                'message' => $notification->getMessage() ?? 'Nouvelle notification',
                'isRead' => (bool) $notification->isRead(),
                'createdAt' => $notification->getCreatedAt()?->format('d/m à H:i') ?? '',
                'csrf' => $csrf->getToken('notif_read'.$id)->getValue(),
            ];
        }

        return $this->json([
            'items' => $items,
            'csrfDeleteAll' => $csrf->getToken('notif_delete_all')->getValue(),
            'csrfReadAll' => $csrf->getToken('notif_read_all')->getValue(),
            'readUrlTemplate' => $this->generateUrl('app_notification_read', ['id' => 0]),
            'readAllUrl' => $this->generateUrl('app_notification_read_all'),
            'deleteAllUrl' => $this->generateUrl('app_notification_delete_all'),
        ]);
    }

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
