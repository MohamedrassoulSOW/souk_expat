<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Api\ApiResourceFactory;
use App\Entity\Category;
use App\Entity\City;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final class CatalogController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly CityRepository $cityRepository,
        private readonly ApiResourceFactory $resources,
    ) {
    }

    #[Route('/categories', name: 'api_v1_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);

        return $this->json([
            'items' => array_map(
                fn (Category $category) => $this->resources->category($category),
                $categories
            ),
        ]);
    }

    #[Route('/cities', name: 'api_v1_cities', methods: ['GET'])]
    public function cities(): JsonResponse
    {
        $cities = $this->cityRepository->findBy([], ['name' => 'ASC']);

        return $this->json([
            'items' => array_map(
                fn (City $city) => $this->resources->city($city),
                $cities
            ),
        ]);
    }
}
