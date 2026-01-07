<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryFormType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    /**
     * Ajouter une catégorie
     */
    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function addCategory(
        EntityManagerInterface $entityManager,
        Request $request,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $category = new Category();
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug($slugger->slug($category->getName())->lower());

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
        Category $category
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category->setSlug($slugger->slug($category->getName())->lower());
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
    #[Route('/{id}/delete', name: 'app_category_delete', methods: ['POST', 'GET'])]
    public function deleteCategory(
        EntityManagerInterface $entityManager,
        Category $category
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', 'Catégorie supprimée !');
        return $this->redirectToRoute('app_category');
    }

    // --- LA MÉTHODE PUBLIQUE A ÉTÉ SUPPRIMÉE D'ICI POUR ÉVITER LE 404 ---
}