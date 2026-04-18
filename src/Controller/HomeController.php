<?php

namespace App\Controller;

use App\DTO\AnnonceSearchFilters;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\AnnonceRepository;
use App\Repository\SliderRepository;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        CategoryRepository $categoryRepository,
        AnnonceRepository $annonceRepository,
        SliderRepository $sliderRepository,
        CityRepository $cityRepository,
        PaginatorInterface $paginator,
        Request $request,
    ): Response {
        $filters = AnnonceSearchFilters::fromRequest($request);

        $query = $annonceRepository->createApprovedSearchQueryBuilder(
            $filters->q,
            $filters->categoryId,
            $filters->cityId,
        )->getQuery();

        $annoncesPaginees = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12,
        );

        return $this->render('home/index.html.twig', [
            'annonces' => $annoncesPaginees,
            'popular_categories' => $categoryRepository->findAllOrderedByName(),
            'search_categories' => $categoryRepository->findAllOrderedByName(),
            'cities' => $cityRepository->findAllOrderedByName(),
            'sliders' => $sliderRepository->findBy(['isActive' => true]),
            'recent_annonces' => $annonceRepository->findRecentApproved(4),
            'search_filters' => $filters,
        ]);
    }
}
