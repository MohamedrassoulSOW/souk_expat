<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Api\ApiResourceFactory;
use App\Entity\Annonce;
use App\Repository\AnnonceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/annonces')]
final class AnnonceController extends AbstractController
{
    public function __construct(
        private readonly AnnonceRepository $annonceRepository,
        private readonly ApiResourceFactory $resources,
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
            return $this->json([
                'error' => 'not_found',
                'message' => 'Annonce introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'item' => $this->resources->annonce($annonce, true),
        ]);
    }
}
