<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AnnonceRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicProfileController extends AbstractController
{
    #[Route('/vendeur/{id}', name: 'app_seller_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        User $user,
        Request $request,
        AnnonceRepository $annonceRepository,
        PaginatorInterface $paginator,
    ): Response {
        if ($user->isBlocked()) {
            throw $this->createNotFoundException('Ce profil n’est pas disponible.');
        }

        $sellerId = (int) $user->getId();
        $stats = $annonceRepository->getSellerPublicStats($sellerId);

        $categoryFilter = $request->query->getInt('category');
        $qb = $annonceRepository->createApprovedByUserQueryBuilder($sellerId, true);
        if ($categoryFilter > 0) {
            $qb->andWhere('category.id = :catFilter')
                ->setParameter('catFilter', $categoryFilter);
        }

        $annonces = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            12,
        );

        $annonceRepository->prefetchImages(iterator_to_array($annonces->getItems()));

        return $this->render('seller/show.html.twig', [
            'seller' => $user,
            'annonces' => $annonces,
            'stats' => $stats,
            'annonces_count' => $stats['count'],
            'category_filter' => $categoryFilter > 0 ? $categoryFilter : null,
            'is_own_profile' => $this->getUser() instanceof User && $this->getUser()->getId() === $user->getId(),
        ]);
    }
}
