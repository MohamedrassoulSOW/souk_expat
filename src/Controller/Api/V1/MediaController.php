<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Annonce;
use App\Entity\AnnonceImage;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\AnnonceImageRepository;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sert les médias stockés en base (BLOB) pour l’app mobile.
 */
#[Route('/api/v1/media')]
final class MediaController extends AbstractController
{
    public function __construct(
        private readonly AnnonceImageRepository $annonceImageRepository,
        private readonly MessageRepository $messageRepository,
    ) {
    }

    #[Route('/annonce-images/{id}', name: 'api_v1_media_annonce_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function annonceImage(int $id): Response
    {
        $image = $this->annonceImageRepository->find($id);
        if (!$image instanceof AnnonceImage || !$image->isStoredInDatabase()) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        $annonce = $image->getAnnonce();
        if (!$annonce instanceof Annonce) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $isOwner = $user instanceof User && $annonce->getUser()?->getId() === $user->getId();
        $isPublic = $annonce->getStatus() === Annonce::STATUS_APPROVED;

        if (!$isPublic && !$isOwner && !$this->isGranted('ROLE_EDITOR')) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        return $this->binaryResponse($image->getContent() ?? '', $image->getMimeType() ?? 'image/jpeg');
    }

    #[Route('/messages/{id}', name: 'api_v1_media_message_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function messageImage(int $id): Response
    {
        $message = $this->messageRepository->find($id);
        if (!$message instanceof Message || !$message->hasDatabaseImage()) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        $thread = $message->getThread();
        $isParticipant = $thread
            && ($thread->getBuyer()?->getId() === $user->getId() || $thread->getSeller()?->getId() === $user->getId());

        if (!$isParticipant && !$this->isGranted('ROLE_EDITOR')) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        return $this->binaryResponse(
            $message->getImageContent() ?? '',
            $message->getImageMimeType() ?? 'image/jpeg'
        );
    }

    private function binaryResponse(string $binary, string $mime): Response
    {
        $response = new Response($binary, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Length' => (string) \strlen($binary),
            'Cache-Control' => 'private, max-age=86400',
        ]);

        return $response;
    }
}
