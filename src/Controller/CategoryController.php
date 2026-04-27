<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryFormType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/category')]
final class CategoryController extends AbstractController
{
    /**
     * Liste des catégories pour l'admin
     */
    #[Route('/', name: 'app_category', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        // Sécurité : Seul l'admin accède à ce fichier
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAllOrderedByName(),
        ]);
    }

    /**
     * Ajouter une catégorie
     */
    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function addCategory(
        EntityManagerInterface $entityManager,
        Request $request,
        SluggerInterface $slugger,
        CategoryRepository $categoryRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $category = new Category();
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $baseSlug = (string) $slugger->slug((string) $category->getName())->lower();
            $category->setSlug($this->resolveUniqueCategorySlug($categoryRepository, $baseSlug, null));

            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile instanceof UploadedFile) {
                $category->setImageName($this->storeCategoryImage($imageFile));
            }

            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Catégorie ajoutée avec succès !');
            return $this->redirectToRoute('app_category');
        }

        return $this->render('category/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Modifier une catégorie
     */
    #[Route('/{id}/update', name: 'app_category_update', methods: ['GET', 'POST'])]
    public function updateCategory(
        EntityManagerInterface $entityManager,
        Request $request,
        SluggerInterface $slugger,
        CategoryRepository $categoryRepository,
        Category $category,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $baseSlug = (string) $slugger->slug((string) $category->getName())->lower();
            $category->setSlug($this->resolveUniqueCategorySlug(
                $categoryRepository,
                $baseSlug,
                $category->getId(),
            ));

            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile instanceof UploadedFile) {
                $this->deleteCategoryImageFile($category->getImageName());
                $category->setImageName($this->storeCategoryImage($imageFile));
            }

            $entityManager->flush();

            $this->addFlash('success', 'Catégorie mise à jour !');
            return $this->redirectToRoute('app_category');
        }

        return $this->render('category/update.html.twig', [
            'form' => $form->createView(),
            'category' => $category,
        ]);
    }

    /**
     * Supprimer une catégorie
     */
    #[Route('/{id}/delete', name: 'app_category_delete', methods: ['POST'])]
    public function deleteCategory(
        Request $request,
        EntityManagerInterface $entityManager,
        Category $category,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if (!$this->isCsrfTokenValid('delete_category_' . $category->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide ou expiré.');

            return $this->redirectToRoute('app_category');
        }

        $this->deleteCategoryImageFile($category->getImageName());

        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', 'Catégorie supprimée !');
        return $this->redirectToRoute('app_category');
    }

    /**
     * Garantit un slug unique (suffixe -2, -3, … si le nom d’affiche produit un slug déjà pris).
     */
    private function resolveUniqueCategorySlug(
        CategoryRepository $categoryRepository,
        string $baseSlug,
        ?int $ignoreCategoryId,
    ): string {
        $slug = $baseSlug;
        $n = 2;
        while (true) {
            $existing = $categoryRepository->findOneBy(['slug' => $slug]);
            if (
                $existing === null
                || ($ignoreCategoryId !== null && $existing->getId() === $ignoreCategoryId)
            ) {
                return $slug;
            }
            $slug = $baseSlug . '-' . $n;
            ++$n;
        }
    }

    private function categoriesUploadDir(): string
    {
        $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/categories';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function storeCategoryImage(UploadedFile $imageFile): string
    {
        $ext = $imageFile->guessExtension() ?: 'jpg';
        $filename = uniqid('cat_', true) . '.' . $ext;
        $imageFile->move($this->categoriesUploadDir(), $filename);

        return $filename;
    }

    private function deleteCategoryImageFile(?string $imageName): void
    {
        if ($imageName === null || $imageName === '') {
            return;
        }
        $path = $this->categoriesUploadDir() . '/' . $imageName;
        if (is_file($path)) {
            unlink($path);
        }
    }
}