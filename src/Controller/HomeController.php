<?php

namespace App\Controller;

use App\DTO\AnnonceSearchFilters;
use App\Service\AnnonceDisplayMixer;
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
        AnnonceDisplayMixer $displayMixer,
    ): Response {
        $filters = AnnonceSearchFilters::fromRequest($request);
        $categories = $categoryRepository->findAllOrderedByName();
        $categoriesWithCounts = $categoryRepository->findWithApprovedCounts();

        // Accueil : récupérer un pool plus large, mélanger puis paginer
        $page = $request->query->getInt('page', 1);
        $perPage = 12;
        $poolSize = $perPage * 5; // prendre un pool plus large pour permettre l'alternance

        $qb = $annonceRepository->createApprovedSearchQueryBuilder(
            $filters->q,
            $filters->categoryId,
            $filters->cityId,
            true,
        );

        $itemsPool = $qb->setMaxResults($poolSize)
            ->getQuery()
            ->getResult();

        $mixedItems = $displayMixer->mix($itemsPool);

        // Paginer à partir du tableau mélangé
        $annoncesPaginees = $paginator->paginate(
            $mixedItems,
            $page,
            $perPage,
        );

        $annonceRepository->prefetchImages(iterator_to_array($annoncesPaginees->getItems()));

        return $this->render('home/index.html.twig', [
            'annonces' => $annoncesPaginees,
            'popular_categories' => $categoriesWithCounts,
            'search_categories' => $categories,
            'cities' => $cityRepository->findAllOrderedByName(),
            'sliders' => $sliderRepository->findBy(['isActive' => true], ['id' => 'DESC'], 5),
            'search_filters' => $filters,
        ]);
    }
}
