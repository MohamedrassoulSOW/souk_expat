<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Api\ApiResourceFactory;
use App\Entity\Annonce;
use App\Entity\Message;
use App\Entity\Thread;
use App\Entity\User;
use App\Repository\AnnonceRepository;
use App\Repository\ThreadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[IsGranted('ROLE_USER')]
final class ThreadController extends AbstractController
{
    public function __construct(
        private readonly ThreadRepository $threadRepository,
        private readonly AnnonceRepository $annonceRepository,
        private readonly ApiResourceFactory $resources,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/threads', name: 'api_v1_threads_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $threads = $this->threadRepository->createQueryBuilder('t')
            ->leftJoin('t.annonce', 'a')->addSelect('a')
            ->leftJoin('a.city', 'city')->addSelect('city')
            ->leftJoin('a.category', 'category')->addSelect('category')
            ->leftJoin('a.user', 'sellerUser')->addSelect('sellerUser')
            ->leftJoin('t.buyer', 'buyer')->addSelect('buyer')
            ->leftJoin('t.seller', 'seller')->addSelect('seller')
            ->where('t.buyer = :user OR t.seller = :user')
            ->setParameter('user', $user)
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        $annonceIds = [];
        foreach ($threads as $thread) {
            if ($thread->getAnnonce()) {
                $annonceIds[] = $thread->getAnnonce()->getId();
            }
        }
        if ($annonceIds !== []) {
            $this->annonceRepository->prefetchImages(
                $this->annonceRepository->findBy(['id' => array_unique($annonceIds)])
            );
        }

        return $this->json([
            'items' => array_map(
                fn (Thread $thread) => $this->resources->thread($thread, $user),
                $threads
            ),
        ]);
    }

    #[Route('/annonces/{id}/thread', name: 'api_v1_annonce_thread', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function openForAnnonce(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $annonce = $this->annonceRepository->find($id);
        if (!$annonce instanceof Annonce || $annonce->getStatus() !== Annonce::STATUS_APPROVED) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Annonce introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($annonce->getUser()?->getId() === $user->getId()) {
            return $this->json([
                'error' => 'forbidden',
                'message' => 'Vous ne pouvez pas discuter sur votre propre annonce.',
            ], Response::HTTP_FORBIDDEN);
        }

        $thread = $this->threadRepository->findOneBy(['annonce' => $annonce, 'buyer' => $user]);
        $created = false;
        if (!$thread instanceof Thread) {
            $thread = new Thread();
            $thread->setAnnonce($annonce);
            $thread->setBuyer($user);
            $thread->setSeller($annonce->getUser());
            $this->em->persist($thread);
            $this->em->flush();
            $created = true;
        }

        return $this->json([
            'item' => $this->resources->thread($thread, $user, true),
            'created' => $created,
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/threads/{id}', name: 'api_v1_threads_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $thread = $this->threadRepository->find($id);
        if (!$thread instanceof Thread || !$this->isParticipant($thread, $user)) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Conversation introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->markThreadRead($thread, $user);

        return $this->json([
            'item' => $this->resources->thread($thread, $user, true),
        ]);
    }

    #[Route('/threads/{id}/messages', name: 'api_v1_threads_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $thread = $this->threadRepository->find($id);
        if (!$thread instanceof Thread || !$this->isParticipant($thread, $user)) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Conversation introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->extractPayload($request);
        $content = trim((string) ($payload['content'] ?? ''));
        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo') ?? $request->files->get('image');
        $latRaw = $payload['latitude'] ?? null;
        $lngRaw = $payload['longitude'] ?? null;
        $locationLabel = trim((string) ($payload['locationLabel'] ?? ''));

        $message = new Message();
        $message->setThread($thread);
        $message->setSender($user);
        $message->setIsRead(false);

        $base64Image = $payload['imageBase64'] ?? $payload['photoBase64'] ?? null;

        if ($photo instanceof UploadedFile && $photo->isValid()) {
            $mime = '';
            if (\function_exists('finfo_open')) {
                $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($photo->getPathname());
                $mime = \is_string($detected) ? $detected : '';
            }
            if ($mime === '') {
                $mime = (string) $photo->getMimeType();
            }
            if (!\in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || @getimagesize($photo->getPathname()) === false) {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Image invalide (jpeg/png/webp).',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($photo->getSize() !== false && $photo->getSize() > 4 * 1024 * 1024) {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Image trop lourde (max 4 Mo).',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $binary = @file_get_contents($photo->getPathname());
            if ($binary === false || $binary === '') {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Impossible de lire l’image.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $message->setKind(Message::KIND_IMAGE);
            $message->setImageFilename(null);
            $message->setImageContent($binary);
            $message->setImageMimeType($mime);
            $message->setContent($content !== '' ? $content : null);
        } elseif (\is_string($base64Image) && $base64Image !== '') {
            $parsed = $this->parseImageBase64($base64Image, isset($payload['mimeType']) ? (string) $payload['mimeType'] : null);
            if ($parsed === null) {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'imageBase64 invalide (jpeg/png/webp, max 4 Mo).',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $message->setKind(Message::KIND_IMAGE);
            $message->setImageFilename(null);
            $message->setImageContent($parsed['binary']);
            $message->setImageMimeType($parsed['mime']);
            $message->setContent($content !== '' ? $content : null);
        } elseif ($latRaw !== null && $latRaw !== '' && $lngRaw !== null && $lngRaw !== '') {
            $lat = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($lngRaw, FILTER_VALIDATE_FLOAT);
            if (
                false === $lat || false === $lng
                || $lat < -90.0 || $lat > 90.0
                || $lng < -180.0 || $lng > 180.0
            ) {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Coordonnées de position invalides.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $message->setKind(Message::KIND_LOCATION);
            $message->setLatitude((float) $lat);
            $message->setLongitude((float) $lng);
            $message->setLocationLabel($locationLabel !== '' ? $locationLabel : null);
            $message->setContent($content !== '' ? $content : null);
        } elseif ($content !== '') {
            $message->setKind(Message::KIND_TEXT);
            $message->setContent($content);
        } else {
            return $this->json([
                'error' => 'validation_error',
                'message' => 'Écrivez un message, ajoutez une photo ou partagez une position.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($message);
        $this->em->flush();

        return $this->json([
            'item' => $this->resources->message($message),
        ], Response::HTTP_CREATED);
    }

    /**
     * @return array{binary: string, mime: string}|null
     */
    private function parseImageBase64(string $raw, ?string $mimeHint): ?array
    {
        $mime = $mimeHint && \in_array($mimeHint, ['image/jpeg', 'image/png', 'image/webp'], true)
            ? $mimeHint
            : 'image/jpeg';

        if (preg_match('#^data:(image/(?:jpeg|png|webp));base64,(.+)$#i', $raw, $m)) {
            $mime = strtolower($m[1]);
            $raw = $m[2];
        }

        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '' || \strlen($binary) > 4 * 1024 * 1024) {
            return null;
        }

        return ['binary' => $binary, 'mime' => $mime];
    }

    private function isParticipant(Thread $thread, User $user): bool
    {
        return $thread->getBuyer()?->getId() === $user->getId()
            || $thread->getSeller()?->getId() === $user->getId()
            || $this->isGranted('ROLE_EDITOR');
    }

    private function markThreadRead(Thread $thread, User $user): void
    {
        $changed = false;
        foreach ($thread->getMessagesAsThread() as $message) {
            if ($message->getSender()?->getId() !== $user->getId() && !$message->isIsRead()) {
                $message->setIsRead(true);
                $changed = true;
            }
        }
        if ($changed) {
            $this->em->flush();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(Request $request): array
    {
        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode($request->getContent() ?: '{}', true);

            return \is_array($payload) ? $payload : [];
        }

        $payload = [];
        foreach (['content', 'latitude', 'longitude', 'locationLabel', 'imageBase64', 'photoBase64', 'mimeType'] as $key) {
            if ($request->request->has($key)) {
                $payload[$key] = $request->request->get($key);
            }
        }

        return $payload;
    }
}
