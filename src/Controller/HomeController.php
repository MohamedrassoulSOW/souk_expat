<?php

namespace App\Controller;

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
        PaginatorInterface $paginator, // Injection du service
        Request $request // Injection de la requête
    ): Response {
        
        // 1. On crée la requête pour les annonces (sans l'exécuter avec ->getResult())
        $query = $annonceRepository->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', 'approved')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery();

        // 2. On pagine cette requête
        $annoncesPaginees = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1), // Numéro de page dans l'URL, 1 par défaut
            12 // Nombre d'annonces par page
        );

        return $this->render('home/index.html.twig', [
            // On passe l'objet de pagination au lieu du tableau simple
            'annonces' => $annoncesPaginees,
            
            'popular_categories' => $categoryRepository->findPopular(6),
            'cities' => $cityRepository->findAll(),
            'sliders' => $sliderRepository->findBy(['isActive' => true]),
            
            // On garde les 4 récentes à part si besoin pour une section fixe
            'recent_annonces' => $annonceRepository->findBy(
                ['status' => 'approved'], 
                ['createdAt' => 'DESC'], 
                4
            ),
        ]);
    }
}