<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Api\ApiAnnonceWriter;
use App\Api\ApiResourceFactory;
use App\Entity\Annonce;
use App\Entity\User;
use App\Repository\AnnonceRepository;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use App\Service\AnnonceDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/annonces')]
final class AnnonceController extends AbstractController
{
    public function __construct(
        private readonly AnnonceRepository $annonceRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly CityRepository $cityRepository,
        private readonly ApiResourceFactory $resources,
        private readonly ApiAnnonceWriter $writer,
        private readonly EntityManagerInterface $em,
        private readonly AnnonceDeletionService $deletionService,
    ) {
    }

    #[Route('', name: 'api_v1_annonces_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $q = $request->query->get('q');
        $category = $request->query->get('category');
        $city = $request->query->get('city');

        $qb = $this->annonceRepository->createApprovedSearchQueryBuilder(
            \is_string($q) ? $q : null,
            is_numeric($category) ? (int) $category : null,
            is_numeric($city) ? (int) $city : null,
            false,
        );

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $this->annonceRepository->prefetchImages($items);

        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'items' => array_map(
                fn (Annonce $annonce) => $this->resources->annonce($annonce),
                $items
            ),
        ]);
    }

    #[Route('', name: 'api_v1_annonces_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = $this->extractPayload($request);

        $errors = $this->validateAnnoncePayload($payload, true);
        if ($errors !== []) {
            return $this->json([
                'error' => 'validation_error',
                'message' => $errors[0],
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = $this->categoryRepository->find((int) $payload['categoryId']);
        $city = $this->cityRepository->find((int) $payload['cityId']);
        if (!$category || !$city) {
            return $this->json([
                'error' => 'validation_error',
                'message' => 'Catégorie ou ville invalide.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $annonce = new Annonce();
        $annonce->setTitle(trim((string) $payload['title']));
        $annonce->setDescription(trim((string) $payload['description']));
        $annonce->setPrice((float) $payload['price']);
        $annonce->setCategory($category);
        $annonce->setCity($city);
        $annonce->setUser($user);
        $annonce->setStatus(Annonce::STATUS_PENDING);
        if (isset($payload['phone'])) {
            $annonce->setPhone(trim((string) $payload['phone']));
        } elseif ($user->getWhatsappPhone()) {
            $annonce->setPhone($user->getWhatsappPhone());
        }

        $this->writer->assignUniqueSlug($annonce);
        $this->em->persist($annonce);

        $images = $this->writer->attachUploadedImages($annonce, $this->writer->collectImageFiles($request));
        $images = array_merge(
            $images,
            $this->writer->attachBase64Images($annonce, $this->writer->collectBase64FromPayload($payload))
        );
        foreach ($images as $image) {
            $this->em->persist($image);
        }

        $this->em->flush();

        return $this->json([
            'item' => $this->resources->annonce($annonce, true),
            'message' => 'Annonce créée. Elle sera visible après validation.',
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_v1_annonces_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $annonce = $this->annonceRepository->createApprovedListingQueryBuilder(false)
            ->andWhere('a.id = :id')
            ->setParameter('id', $id)
            ->leftJoin('a.annonceImages', 'img')->addSelect('img')
            ->getQuery()
            ->getOneOrNullResult();

        if (!$annonce instanceof Annonce) {
            // Propriétaire / éditeur : accès même si non approuvée
            $annonce = $this->annonceRepository->find($id);
            $user = $this->getUser();
            $isOwner = $user instanceof User && $annonce && $annonce->getUser()?->getId() === $user->getId();
            if (!$annonce || (!$isOwner && !$this->isGranted('ROLE_EDITOR'))) {
                return $this->json([
                    'error' => 'not_found',
                    'message' => 'Annonce introuvable.',
                ], Response::HTTP_NOT_FOUND);
            }
        }

        return $this->json([
            'item' => $this->resources->annonce($annonce, true),
        ]);
    }

    #[Route('/{id}', name: 'api_v1_annonces_update', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(int $id, Request $request): JsonResponse
    {
        $annonce = $this->annonceRepository->find($id);
        if (!$annonce instanceof Annonce) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Annonce introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canManage($annonce)) {
            return $this->json([
                'error' => 'forbidden',
                'message' => 'Vous ne pouvez pas modifier cette annonce.',
            ], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->extractPayload($request);
        $errors = $this->validateAnnoncePayload($payload, false);
        if ($errors !== []) {
            return $this->json([
                'error' => 'validation_error',
                'message' => $errors[0],
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (isset($payload['title'])) {
            $annonce->setTitle(trim((string) $payload['title']));
            $this->writer->assignUniqueSlug($annonce, $annonce->getId());
        }
        if (isset($payload['description'])) {
            $annonce->setDescription(trim((string) $payload['description']));
        }
        if (isset($payload['price'])) {
            $annonce->setPrice((float) $payload['price']);
        }
        if (isset($payload['phone'])) {
            $annonce->setPhone(trim((string) $payload['phone']));
        }
        if (isset($payload['categoryId'])) {
            $category = $this->categoryRepository->find((int) $payload['categoryId']);
            if (!$category) {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Catégorie invalide.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $annonce->setCategory($category);
        }
        if (isset($payload['cityId'])) {
            $city = $this->cityRepository->find((int) $payload['cityId']);
            if (!$city) {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Ville invalide.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $annonce->setCity($city);
        }

        $annonce->setUpdatedAt(new \DateTimeImmutable());

        $images = $this->writer->attachUploadedImages($annonce, $this->writer->collectImageFiles($request));
        $images = array_merge(
            $images,
            $this->writer->attachBase64Images($annonce, $this->writer->collectBase64FromPayload($payload))
        );
        foreach ($images as $image) {
            $this->em->persist($image);
        }

        $this->em->flush();

        return $this->json([
            'item' => $this->resources->annonce($annonce, true),
            'message' => 'Annonce mise à jour.',
        ]);
    }

    #[Route('/{id}/images', name: 'api_v1_annonces_images', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addImages(int $id, Request $request): JsonResponse
    {
        $annonce = $this->annonceRepository->find($id);
        if (!$annonce instanceof Annonce) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Annonce introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canManage($annonce)) {
            return $this->json([
                'error' => 'forbidden',
                'message' => 'Vous ne pouvez pas modifier cette annonce.',
            ], Response::HTTP_FORBIDDEN);
        }

        $files = $this->writer->collectImageFiles($request);
        $payload = $this->extractPayload($request);
        $base64Items = $this->writer->collectBase64FromPayload($payload);
        if ($files === [] && $base64Items === []) {
            return $this->json([
                'error' => 'validation_error',
                'message' => 'Aucuneune image valide. Envoyez images[] (multipart) ou imagesBase64 (JSON). Stockage en base uniquement.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $remaining = max(0, 8 - $annonce->getAnnonceImages()->count());
        $images = $this->writer->attachUploadedImages($annonce, $files, $remaining);
        $left = max(0, $remaining - \count($images));
        $images = array_merge($images, $this->writer->attachBase64Images($annonce, $base64Items, $left));
        foreach ($images as $image) {
            $this->em->persist($image);
        }
        $annonce->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json([
            'item' => $this->resources->annonce($annonce, true),
            'added' => \count($images),
        ]);
    }

    #[Route('/{id}', name: 'api_v1_annonces_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id): JsonResponse
    {
        $annonce = $this->annonceRepository->find($id);
        if (!$annonce instanceof Annonce) {
            return $this->json([
                'error' => 'not_found',
                'message' => 'Annonce introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->canManage($annonce)) {
            return $this->json([
                'error' => 'forbidden',
                'message' => 'Vous ne pouvez pas supprimer cette annonce.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->deletionService->removeCompletely($this->em, $annonce);
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'message' => 'Annonce supprimée.',
        ]);
    }

    private function canManage(Annonce $annonce): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $annonce->getUser()?->getId() === $user->getId() || $this->isGranted('ROLE_EDITOR');
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

        // multipart / form-urlencoded
        $payload = [];
        foreach (['title', 'description', 'price', 'categoryId', 'cityId', 'phone'] as $key) {
            if ($request->request->has($key)) {
                $payload[$key] = $request->request->get($key);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function validateAnnoncePayload(array $payload, bool $requireAll): array
    {
        $errors = [];

        if ($requireAll || \array_key_exists('title', $payload)) {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '' || mb_strlen($title) < 3) {
                $errors[] = 'Le titre doit contenir au moins 3 caractères.';
            }
        }

        if ($requireAll || \array_key_exists('description', $payload)) {
            $description = trim((string) ($payload['description'] ?? ''));
            if ($description === '' || mb_strlen($description) < 10) {
                $errors[] = 'La description doit contenir au moins 10 caractères.';
            }
        }

        if ($requireAll || \array_key_exists('price', $payload)) {
            if (!isset($payload['price']) || !is_numeric($payload['price']) || (float) $payload['price'] < 0) {
                $errors[] = 'Le prix est invalide.';
            }
        }

        if ($requireAll || \array_key_exists('categoryId', $payload)) {
            if (!isset($payload['categoryId']) || !is_numeric($payload['categoryId'])) {
                $errors[] = 'categoryId est requis.';
            }
        }

        if ($requireAll || \array_key_exists('cityId', $payload)) {
            if (!isset($payload['cityId']) || !is_numeric($payload['cityId'])) {
                $errors[] = 'cityId est requis.';
            }
        }

        return $errors;
    }
}
