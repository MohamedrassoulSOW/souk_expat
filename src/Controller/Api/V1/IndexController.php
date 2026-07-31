<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final class IndexController extends AbstractController
{
    #[Route('', name: 'api_v1_index', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'name' => 'SoukExpat Mobile API',
            'version' => 'v1',
            'endpoints' => [
                'POST /api/v1/auth/login' => 'Connexion (email + password) → JWT',
                'POST /api/v1/auth/register' => 'Inscription (email, password, firstName, lastName, acceptTerms) → JWT',
                'GET /api/v1/me' => 'Profil connecté (Bearer)',
                'PATCH /api/v1/me' => 'Mettre à jour prénom/nom (Bearer)',
                'GET /api/v1/annonces' => 'Liste publique (q, category, city, page, limit)',
                'GET /api/v1/annonces/{id}' => 'Détail annonce approuvée',
                'GET /api/v1/me/annonces' => 'Mes annonces (Bearer)',
                'GET /api/v1/categories' => 'Catégories',
                'GET /api/v1/cities' => 'Villes',
            ],
        ]);
    }
}
