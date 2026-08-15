<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Api\ApiResourceFactory;
use App\Entity\Annonce;
use App\Entity\User;
use App\Repository\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1')]
#[IsGranted('ROLE_USER')]
final class MeController extends AbstractController
{
    public function __construct(
        private readonly ApiResourceFactory $resources,
        private readonly EntityManagerInterface $em,
        private readonly AnnonceRepository $annonceRepository,
    ) {
    }

    #[Route('/me', name: 'api_v1_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['user' => $this->resources->user($user, true)]);
    }

    #[Route('/me', name: 'api_v1_me_update', methods: ['PATCH', 'PUT'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!\is_array($payload)) {
            $payload = [];
        }

        unset($payload['email'], $payload['password'], $payload['roles']);

        if (isset($payload['firstName'])) {
            $firstName = trim((string) $payload['firstName']);
            if ($firstName === '') {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Le prénom ne peut pas être vide.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->setFirstName($firstName);
        }

        if (isset($payload['lastName'])) {
            $lastName = trim((string) $payload['lastName']);
            if ($lastName === '') {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Le nom ne peut pas être vide.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->setLastName($lastName);
        }

        if (\array_key_exists('whatsappPhone', $payload)) {
            $phone = trim((string) ($payload['whatsappPhone'] ?? ''));
            if (mb_strlen($phone) > 30) {
                return $this->json([
                    'error' => 'validation_error',
                    'message' => 'Numéro WhatsApp trop long.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->setWhatsappPhone($phone !== '' ? $phone : null);
        }

        $this->em->flush();

        return $this->json(['user' => $this->resources->user($user, true)]);
    }

    #[Route('/me/annonces', name: 'api_v1_me_annonces', methods: ['GET'])]
    public function myAnnonces(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->annonceRepository->createQueryBuilder('a')
            ->innerJoin('a.city', 'city')->addSelect('city')
            ->innerJoin('a.category', 'category')->addSelect('category')
            ->leftJoin('a.user', 'seller')->addSelect('seller')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC');

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
                fn (Annonce $annonce) => $this->resources->annonce($annonce, true),
                $items
            ),
        ]);
    }
}
