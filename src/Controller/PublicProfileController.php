<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AnnonceRepository;
use App\Service\AnnonceDisplayMixer;
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
        AnnonceDisplayMixer $displayMixer,
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
            self::resolveListingPerPage($request),
        );

        $items = iterator_to_array($annonces->getItems());
        $mixedItems = $displayMixer->mix($items);
        $annonceRepository->prefetchImages($mixedItems);

        $annonces->setItems($mixedItems);

        $currentUser = $this->getUser();

        return $this->render('seller/show.html.twig', [
            'seller' => $user,
            'annonces' => $annonces,
            'stats' => $stats,
            'annonces_count' => $stats['count'],
            'category_filter' => $categoryFilter > 0 ? $categoryFilter : null,
            'is_own_profile' => $currentUser instanceof User && $currentUser->getId() === $user->getId(),
        ]);
    }

    private static function resolveListingPerPage(Request $request): int
    {
        $userAgent = mb_strtolower((string) $request->headers->get('User-Agent', ''));

        return preg_match('/mobile|android|iphone|ipod|iemobile|blackberry|opera mini/i', $userAgent) ? 16 : 24;
    }
}
