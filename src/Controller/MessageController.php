<?php

namespace App\Controller;

use App\Entity\Thread;
use App\Entity\Message;
use App\Entity\Annonce;
use App\Entity\User;
use App\Form\MessageType;
use App\Repository\ThreadRepository;
use App\Service\SafeImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chat')]
class MessageController extends AbstractController
{
    /**
     * Liste toutes les conversations
     */
    #[Route('/messages', name: 'app_messages_list')]
    public function index(ThreadRepository $threadRepo): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $threads = $threadRepo->createQueryBuilder('t')
            ->where('t.buyer = :user OR t.seller = :user')
            ->setParameter('user', $user)
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('message/index.html.twig', [
            'threads' => $threads,
        ]);
    }

    /**
     * Crée ou récupère une discussion
     */
    #[Route('/check/{id}', name: 'app_chat_check')]
    public function check(Annonce $annonce, ThreadRepository $threadRepo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($annonce->getUser() && $user->getId() === $annonce->getUser()->getId()) {
            $this->addFlash('warning', 'Action impossible sur votre propre annonce.');
            return $this->redirectToRoute('app_annonce_index');
        }

        if ($annonce->getStatus() !== Annonce::STATUS_APPROVED) {
            $this->addFlash('warning', 'Cette annonce n’est pas encore disponible pour la messagerie.');
            return $this->redirectToRoute('app_annonce_index');
        }

        $thread = $threadRepo->findOneBy(['annonce' => $annonce, 'buyer' => $user]);

        if (!$thread) {
            $thread = new Thread();
            $thread->setAnnonce($annonce);
            $thread->setBuyer($user);
            $thread->setSeller($annonce->getUser());
            $em->persist($thread);
            $em->flush();
        }

        return $this->redirectToRoute('app_chat_view', ['id' => $thread->getId()]);
    }

    /**
     * Affiche la discussion et diminue le compteur de notifications
     */
    #[Route('/thread/{id}', name: 'app_chat_view')]
    public function view(Thread $thread, Request $request, EntityManagerInterface $em, SafeImageUploader $imageUploader): Response 
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $buyerId = $thread->getBuyer()?->getId();
        $sellerId = $thread->getSeller()?->getId();
        $isParticipant = $user->getId() === $buyerId || $user->getId() === $sellerId;
        $canViewAsStaff = $this->isGranted('ROLE_EDITOR');
        if (!$isParticipant && !$canViewAsStaff) {
            throw $this->createAccessDeniedException();
        }

        if ($isParticipant) {
            $hasUnread = false;
            foreach ($thread->getMessagesAsThread() as $message) {
                if ($message->getSender()?->getId() !== $user->getId() && !$message->isIsRead()) {
                    $message->setIsRead(true);
                    $hasUnread = true;
                }
            }

            if ($hasUnread) {
                $em->flush();
            }
        } elseif ($canViewAsStaff) {
            // Éditeur / admin : lecture seule, ne pas marquer « lu » pour les utilisateurs
            return $this->render('message/view.html.twig', [
                'thread' => $thread,
                'form' => null,
                'adminReadOnly' => true,
            ]);
        }

        $newMessage = new Message();
        $form = $this->createForm(MessageType::class, $newMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $caption = trim((string) ($newMessage->getContent() ?? ''));

            /** @var UploadedFile|null $photo */
            $photo = $form->get('photo')->getData();
            $latRaw = $form->get('latitude')->getData();
            $lngRaw = $form->get('longitude')->getData();
            $locLabelRaw = $form->get('locationLabel')->getData();
            $locationLabel = \is_string($locLabelRaw) ? trim($locLabelRaw) : '';

            $uploadRoot = $this->getParameter('kernel.project_dir') . '/public/uploads/messages';
            if (!is_dir($uploadRoot)) {
                mkdir($uploadRoot, 0775, true);
            }

            if ($photo instanceof UploadedFile) {
                $newMessage->setKind(Message::KIND_IMAGE);
                try {
                    $filename = $imageUploader->store(
                        $photo,
                        $uploadRoot,
                        4 * 1024 * 1024,
                        ['image/jpeg', 'image/png', 'image/webp'],
                        $thread->getId().'_',
                    );
                } catch (FileException) {
                    $this->addFlash('danger', 'Impossible d’envoyer cette image.');

                    return $this->render('message/view.html.twig', [
                        'thread' => $thread,
                        'form' => $form->createView(),
                        'adminReadOnly' => false,
                    ]);
                }
                $newMessage->setImageFilename($filename);
                $newMessage->setContent($caption !== '' ? $caption : null);
            } elseif (($latRaw !== null && $latRaw !== '') && ($lngRaw !== null && $lngRaw !== '')) {
                $lat = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
                $lng = filter_var($lngRaw, FILTER_VALIDATE_FLOAT);
                if (
                    false === $lat || false === $lng
                    || $lat < -90.0 || $lat > 90.0
                    || $lng < -180.0 || $lng > 180.0
                ) {
                    $this->addFlash('danger', 'Les coordonnées de position sont invalides.');
                    return $this->render('message/view.html.twig', [
                        'thread' => $thread,
                        'form' => $form->createView(),
                        'adminReadOnly' => false,
                    ]);
                }
                $newMessage->setKind(Message::KIND_LOCATION);
                $newMessage->setLatitude((float) $lat);
                $newMessage->setLongitude((float) $lng);
                $newMessage->setLocationLabel($locationLabel !== '' ? $locationLabel : null);
                $newMessage->setContent($caption !== '' ? $caption : null);
            } elseif ($caption !== '') {
                $newMessage->setKind(Message::KIND_TEXT);
                $newMessage->setContent($caption);
            } else {
                $this->addFlash('warning', 'Écrivez un message, ajoutez une photo ou partagez votre position.');

                return $this->render('message/view.html.twig', [
                    'thread' => $thread,
                    'form' => $form->createView(),
                    'adminReadOnly' => false,
                ]);
            }

            $newMessage->setThread($thread);
            $newMessage->setSender($user);
            $newMessage->setIsRead(false);

            $em->persist($newMessage);
            $em->flush();

            return $this->redirectToRoute('app_chat_view', ['id' => $thread->getId()]);
        }

        return $this->render('message/view.html.twig', [
            'thread' => $thread,
            'form' => $form->createView(),
            'adminReadOnly' => false,
        ]);
    }
}