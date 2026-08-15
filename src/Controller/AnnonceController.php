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
use App\Service\AnnonceDisplayMixer;
use App\Service\NotificationService;
use App\Service\SafeImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        AnnonceDisplayMixer $displayMixer,
    ): Response {
        $filters = AnnonceSearchFilters::fromRequest($request);
        $page = $request->query->getInt('page', 1);
        $perPage = 12;
        $poolSize = max(60, $perPage * 8);

        $qb = $annonceRepository->createApprovedSearchQueryBuilder(
            $filters->q,
            $filters->categoryId,
            $filters->cityId,
            false,
        );

        $itemsPool = $qb->setMaxResults($poolSize)
            ->getQuery()
            ->getResult();

        $mixedItems = $displayMixer->mix($itemsPool, 3);

        $annonces = $paginator->paginate(
            $mixedItems,
            $page,
            $perPage,
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

        if (!$this->isCsrfTokenValid('admin_notify_all', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_dashboard');
        }

        $title = mb_substr(trim((string) $request->request->get('title')), 0, 120);
        $message = mb_substr(trim((string) $request->request->get('message')), 0, 500);

        if ($title !== '' && $message !== '') {
            $notifService->notifyAll($title, $message);
            $this->addFlash('success', 'Information envoyée à tous les utilisateurs.');
        } else {
            $this->addFlash('warning', 'Titre et message requis.');
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
        AnnonceDisplayMixer $displayMixer,
    ): Response {
        $category = $categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException("Catégorie introuvable");
        }

        $filters = AnnonceSearchFilters::fromRequest($request);
        $page = $request->query->getInt('page', 1);
        $perPage = 12;
        $poolSize = max(60, $perPage * 8);

        $qb = $annonceRepository->createApprovedSearchQueryBuilder(
            $filters->q,
            $category->getId(),
            $filters->cityId,
            false,
        );

        $itemsPool = $qb->setMaxResults($poolSize)
            ->getQuery()
            ->getResult();

        $mixedItems = $displayMixer->mix($itemsPool, 3);

        $annonces = $paginator->paginate(
            $mixedItems,
            $page,
            $perPage,
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
        AnnonceRepository $annonceRepository,
        SafeImageUploader $imageUploader,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $annonce = new Annonce();
        if ($user->getWhatsappPhone()) {
            $annonce->setPhone($user->getWhatsappPhone());
        }
        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $annonce->setUser($user);
            $annonce->setStatus(Annonce::STATUS_PENDING);
            if ($annonce->getPhone() === '' && $user->getWhatsappPhone()) {
                $annonce->setPhone($user->getWhatsappPhone());
            }

            // Gestion du Slug Unique
            $baseSlug = $slugger->slug($annonce->getTitle())->lower();
            $slug = $baseSlug;
            $i = 1;
            while ($annonceRepository->findOneBy(['slug' => $slug])) {
                $slug = $baseSlug . '-' . $i;
                $i++;
            }
            $annonce->setSlug($slug);

            // Upload des images (plusieurs autorisées, max 8 au total)
            $images = $form->get('images')->getData() ?? [];
            if (!\is_array($images)) {
                $images = $images ? [$images] : [];
            }
            $images = \array_slice($images, 0, 8);
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/annonces';
            foreach ($images as $image) {
                if (!$image instanceof UploadedFile) {
                    continue;
                }
                try {
                    $filename = $imageUploader->store(
                        $image,
                        $uploadDir,
                        4 * 1024 * 1024,
                        ['image/jpeg', 'image/png', 'image/webp'],
                    );
                } catch (FileException) {
                    $this->addFlash('warning', 'Une photo n’a pas pu être enregistrée (format ou fichier invalide).');
                    continue;
                }

                $annonceImage = new AnnonceImage();
                $annonceImage->setImadeName($filename);
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
        AnnonceRepository $annonceRepository,
        SafeImageUploader $imageUploader,
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

            $images = $form->get('images')->getData() ?? [];
            if (!\is_array($images)) {
                $images = $images ? [$images] : [];
            }
            $remaining = max(0, 8 - $annonce->getAnnonceImages()->count());
            if (\count($images) > $remaining) {
                $this->addFlash('warning', sprintf(
                    'Maximum 8 photos par annonce. %d photo(s) ajoutée(s) (places restantes).',
                    $remaining
                ));
                $images = \array_slice($images, 0, $remaining);
            }
            foreach ($images as $image) {
                if (!$image instanceof UploadedFile) {
                    continue;
                }
                try {
                    $filename = $imageUploader->store(
                        $image,
                        $this->getParameter('kernel.project_dir') . '/public/uploads/annonces',
                        4 * 1024 * 1024,
                        ['image/jpeg', 'image/png', 'image/webp'],
                    );
                } catch (FileException) {
                    $this->addFlash('warning', 'Une photo n’a pas pu être enregistrée (format ou fichier invalide).');
                    continue;
                }

                $annonceImage = new AnnonceImage();
                $annonceImage->setImadeName($filename);
                $annonceImage->setAnnonce($annonce);
                $entityManager->persist($annonceImage);
            }

            // Toute modification remet l’annonce en modération (sauf actions admin)
            if (!$this->isGranted('ROLE_EDITOR')) {
                $annonce->setStatus(Annonce::STATUS_PENDING);
                $annonce->setApprovedAt(null);
            }

            $entityManager->flush();
            if (!$this->isGranted('ROLE_EDITOR') && $annonce->getStatus() === Annonce::STATUS_PENDING) {
                $this->addFlash('success', 'Annonce mise à jour — elle sera de nouveau visible après validation.');
            } else {
                $this->addFlash('success', 'Annonce mise à jour');
            }
            return $this->redirectToRoute('app_mes_annonces');
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

    #[Route('/{id}/image/{imageId}/delete', name: 'app_annonce_image_delete', methods: ['POST'], requirements: ['id' => '\d+', 'imageId' => '\d+'])]
    public function deleteImage(
        Annonce $annonce,
        int $imageId,
        Request $request,
        EntityManagerInterface $entityManager,
        AnnonceDeletionService $annonceDeletionService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($annonce->getUser() !== $this->getUser() && !$this->isGranted('ROLE_EDITOR')) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_image' . $imageId, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_annonce_edit', ['id' => $annonce->getId()]);
        }

        $image = null;
        foreach ($annonce->getAnnonceImages() as $candidate) {
            if ($candidate->getId() === $imageId) {
                $image = $candidate;
                break;
            }
        }

        if (!$image) {
            $this->addFlash('danger', 'Photo introuvable.');

            return $this->redirectToRoute('app_annonce_edit', ['id' => $annonce->getId()]);
        }

        $annonceDeletionService->removeImage($entityManager, $image);
        $entityManager->flush();
        $this->addFlash('success', 'Photo supprimée.');

        return $this->redirectToRoute('app_annonce_edit', ['id' => $annonce->getId()]);
    }

    #[Route('/mes-annonces/{id}', name: 'app_mes_annonces_show', methods: ['GET'])]
    public function mesAnnoncesShow(Annonce $annonce): Response
    {
        // On redirige simplement vers la méthode show existante 
        // ou on affiche la vue directement
        return $this->show($annonce);
    }
}