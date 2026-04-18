<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\AnnonceRepository;
use App\Repository\SliderRepository;   // <-- Ajouté
use App\Repository\CategoryRepository; // <-- Ajouté
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\ContactRepository; // <-- Ajouté
use App\Repository\ThreadRepository;

#[IsGranted('ROLE_EDITOR')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        UserRepository $userRepository, 
        AnnonceRepository $annonceRepository,
        SliderRepository $sliderRepository,   // <-- Injecté ici
        CategoryRepository $categoryRepository, // <-- Injecté ici
        ContactRepository $contactRepository, // <-- Injecté ici
        ThreadRepository $threadRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $utilisateurs = [];
        $userCount = 0;
        $annonceCount = 0;
        $pendingCount = 0;
        $approvedCount = 0;
        $rejectedCount = 0;
        $messageCount = 0;
        $messageCountTrue = 0;
        $chatThreadCount = 0;
        
        // Initialisation avec les vraies valeurs
        $sliderCount = $sliderRepository->count([]);     // <-- Récupère le vrai nombre
        $categoryCount = $categoryRepository->count([]); // <-- Récupère le vrai nombre

        if ($this->isGranted('ROLE_EDITOR')) {
            $messageCount = $contactRepository->count(['isProcessed' => false]);
            $messageCountTrue = $contactRepository->count(['isProcessed' => true]);
            $pendingCount = $annonceRepository->count(['status' => 'pending']);
            $approvedCount = $annonceRepository->count(['status' => 'approved']);
            $rejectedCount = $annonceRepository->count(['status' => 'rejected']);
            $chatThreadCount = $threadRepository->count([]);
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            $utilisateurs = $userRepository->findAll();
            $userCount = $userRepository->count([]);
        }

        $user = $this->getUser();
        if ($user) {
            $annonceCount = $annonceRepository->count(['user' => $user]);
        }

        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
            'utilisateurs' => $utilisateurs,
            'userCount' => $userCount,
            'annonceCount' => $annonceCount,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
            'sliderCount' => $sliderCount,
            'categoryCount' => $categoryCount,
            'messageCount' => $messageCount,
            'messageCountTrue' => $messageCountTrue,
            'chatThreadCount' => $chatThreadCount,
        ]);
    }
}