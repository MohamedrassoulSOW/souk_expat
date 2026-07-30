<?php

namespace App\Controller;

use App\DTO\AnnonceSearchFilters;
use App\Entity\Annonce;
use App\Entity\AnnonceImage;
use App\Form\AnnonceType;
use App\Repository\AnnonceRepository;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use App\Service\AnnonceDeletionService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/annonce')]
final class AnnonceController extends AbstractController
{
    /**
     * Liste globale des annonces (Public)
     */
    #[Route('/', name: 'app_annonce_index', methods: ['GET'])]
    public function index(
        AnnonceRepository $annonceRepository,
        CategoryRepository $categoryRepository,
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

        $annonces = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12,
        );

        $annonceRepository->prefetchImages(iterator_to_array($annonces->getItems()));

        return $this->render('annonce/index.html.twig', [
            'annonces' => $annonces,
            'search_categories' => $categoryRepository->findAllOrderedByName(),
            'cities' => $cityRepository->findAllOrderedByName(),
            'search_filters' => $filters,
        ]);
    }

    #[Route('/admin/notify-all', name: 'admin_notify_all', methods: ['POST'])]
    public function notifyAllUsers(Request $request, NotificationService $notifService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');
        $title = $request->request->get('title');
        $message = $request->request->get('message');

        if ($title && $message) {
            $notifService->notifyAll($title, $message);
            $this->addFlash('success', 'Information envoyée à tous les utilisateurs !');
        }

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * Liste par catégorie
     */
    #[Route('/category/{slug}', name: 'app_annonce_category', methods: ['GET'])]
    public function category(
        string $slug,
        CategoryRepository $categoryRepository,
        CityRepository $cityRepository,
        AnnonceRepository $annonceRepository,
        PaginatorInterface $paginator,
        Request $request,
    ): Response {
        $category = $categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException("Catégorie introuvable");
        }

        $filters = AnnonceSearchFilters::fromRequest($request);

        $query = $annonceRepository->createApprovedSearchQueryBuilder(
            $filters->q,
            $category->getId(),
            $filters->cityId,
        )->getQuery();

        $annonces = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12,
        );

        $annonceRepository->prefetchImages(iterator_to_array($annonces->getItems()));

        return $this->render('annonce/index.html.twig', [
            'annonces' => $annonces,
            'category' => $category,
            'search_categories' => $categoryRepository->findAllOrderedByName(),
            'cities' => $cityRepository->findAllOrderedByName(),
            'search_filters' => $filters,
        ]);
    }

    /**
     * Dashboard de l'utilisateur (Connecté)
     */
    #[Route('/mes-annonces', name: 'app_mes_annonces')]
    public function mesAnnonces(AnnonceRepository $annonceRepository, PaginatorInterface $paginator, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) return $this->redirectToRoute('app_login');

        $query = $annonceRepository->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery();

        $annonces = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12 // 12 par page dans le tableau de gestion
        );

        return $this->render('annonce/mes_annonces.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    /**
     * Créer une nouvelle annonce (Connecté)
     */
    #[Route('/new', name: 'app_annonce_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        AnnonceRepository $annonceRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $annonce = new Annonce();
        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $annonce->setUser($this->getUser());
            $annonce->setStatus(Annonce::STATUS_PENDING);

            // Gestion du Slug Unique
            $baseSlug = $slugger->slug($annonce->getTitle())->lower();
            $slug = $baseSlug;
            $i = 1;
            while ($annonceRepository->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug . '-' . $i;
                $i++;
            }
            $annonce->setSlug($slug);

            // Upload des images
            $images = $form->get('images')->getData();
            foreach ($images as $image) {
                $filename = uniqid() . '.' . $image->guessExtension();
                $image->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/annonces',
                    $filename
                );

                $annonceImage = new AnnonceImage();
                $annonceImage->setImadeName($filename); // Corrigé de imadeName à imageName
                $annonceImage->setAnnonce($annonce);
                $entityManager->persist($annonceImage);
            }

            $entityManager->persist($annonce);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce créée avec succès, elle sera visible après validation.');
            return $this->redirectToRoute('app_annonce_index');
        }

        return $this->render('annonce/new.html.twig', [
            'annonce' => $annonce,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Voir une annonce précise (Public si approuvée)
     */
    #[Route('/{id}', name: 'app_annonce_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(?Annonce $annonce): Response
    {
        if (!$annonce) {
            throw $this->createNotFoundException("Cette annonce n'existe pas.");
        }

        if ($annonce->getStatus() !== Annonce::STATUS_APPROVED) {
            if (!$this->isGranted('ROLE_EDITOR') && $this->getUser() !== $annonce->getUser()) {
                throw $this->createAccessDeniedException("Cette annonce est en cours de modération.");
            }
        }

        return $this->render('annonce/show.html.twig', [
            'annonce' => $annonce,
        ]);
    }

    /**
     * Modifier une annonce (Propriétaire uniquement)
     */
    #[Route('/{id}/edit', name: 'app_annonce_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Annonce $annonce,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        AnnonceRepository $annonceRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($annonce->getUser() !== $this->getUser() && !$this->isGranted('ROLE_EDITOR')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $baseSlug = $slugger->slug($annonce->getTitle())->lower();
            $slug = $baseSlug;
            $i = 1;
            while (($existing = $annonceRepository->findOneBy(['slug' => $slug])) && $existing->getId() !== $annonce->getId()) {
                $slug = $baseSlug . '-' . $i;
                $i++;
            }
            $annonce->setSlug($slug);
            $annonce->setUpdatedAt(new \DateTimeImmutable());

            $images = $form->get('images')->getData();
            foreach ($images as $image) {
                $filename = uniqid() . '.' . $image->guessExtension();
                $image->move($this->getParameter('kernel.project_dir') . '/public/uploads/annonces', $filename);

                $annonceImage = new AnnonceImage();
                $annonceImage->setImadeName($filename);
                $annonceImage->setAnnonce($annonce);
                $entityManager->persist($annonceImage);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Annonce mise à jour');
            return $this->redirectToRoute('app_annonce_index');
        }

        return $this->render('annonce/edit.html.twig', [
            'annonce' => $annonce,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_annonce_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Annonce $annonce,
        EntityManagerInterface $entityManager,
        AnnonceDeletionService $annonceDeletionService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Sécurité : Seul le propriétaire ou l'admin peut supprimer
        if ($annonce->getUser() !== $this->getUser() && !$this->isGranted('ROLE_EDITOR')) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de supprimer cette annonce.");
        }

        if ($this->isCsrfTokenValid('delete' . $annonce->getId(), $request->request->get('_token'))) {
            $annonceDeletionService->removeCompletely($entityManager, $annonce);
            $entityManager->flush();

            $this->addFlash('success', 'Annonce supprimée avec succès.');
        }

        return $this->redirectToRoute('app_annonce_index');
    }

    #[Route('/mes-annonces/{id}', name: 'app_mes_annonces_show', methods: ['GET'])]
    public function mesAnnoncesShow(Annonce $annonce): Response
    {
        // On redirige simplement vers la méthode show existante 
        // ou on affiche la vue directement
        return $this->show($annonce);
    }
}