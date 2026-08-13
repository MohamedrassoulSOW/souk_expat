<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\AnnonceRepository;
use App\Repository\SliderRepository;
use App\Repository\CategoryRepository;
use App\Service\AnnonceReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\ContactRepository;
use App\Repository\ThreadRepository;

#[IsGranted('ROLE_EDITOR')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        UserRepository $userRepository, 
        AnnonceRepository $annonceRepository,
        SliderRepository $sliderRepository,
        CategoryRepository $categoryRepository,
        ContactRepository $contactRepository,
        ThreadRepository $threadRepository,
        AnnonceReportService $annonceReportService,
    ): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $utilisateurs = [];
        $userCount = 0;
        $listingStats = [];
        $annonceCount = 0;
        $annoncesTotalCount = 0;
        $pendingCount = 0;
        $approvedCount = 0;
        $rejectedCount = 0;
        $draftCount = 0;
        $messageCount = 0;
        $messageCountTrue = 0;
        $chatThreadCount = 0;

        $sliderCount = $sliderRepository->count([]);
        $categoryCount = $categoryRepository->count([]);

        if ($this->isGranted('ROLE_EDITOR')) {
            $messageCount = $contactRepository->count(['isProcessed' => false]);
            $messageCountTrue = $contactRepository->count(['isProcessed' => true]);
            $listingTotals = $annonceReportService->dashboardTotals();
            $annoncesTotalCount = $listingTotals['total'];
            $pendingCount = $listingTotals['pending'];
            $approvedCount = $listingTotals['approved'];
            $rejectedCount = $listingTotals['rejected'];
            $draftCount = $listingTotals['draft'];
            $chatThreadCount = $threadRepository->count([]);
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            $utilisateurs = $userRepository->findBy([], ['id' => 'DESC']);
            $userCount = count($utilisateurs);
            $listingStats = $annonceRepository->countStatsIndexedByUserId();
        }

        $user = $this->getUser();
        if ($user) {
            $annonceCount = $annonceRepository->count(['user' => $user]);
        }

        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
            'utilisateurs' => $utilisateurs,
            'userCount' => $userCount,
            'listingStats' => $listingStats,
            'annonceCount' => $annonceCount,
            'annoncesTotalCount' => $annoncesTotalCount,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
            'draftCount' => $draftCount,
            'sliderCount' => $sliderCount,
            'categoryCount' => $categoryCount,
            'messageCount' => $messageCount,
            'messageCountTrue' => $messageCountTrue,
            'chatThreadCount' => $chatThreadCount,
        ]);
    }
}