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
                'POST /api/v1/auth/register' => 'Inscription → JWT',
                'GET /api/v1/me' => 'Profil connecté (Bearer)',
                'PATCH /api/v1/me' => 'Mettre à jour prénom/nom (Bearer)',
                'GET /api/v1/me/annonces' => 'Mes annonces (Bearer)',
                'GET /api/v1/annonces' => 'Liste publique (q, category, city, page, limit)',
                'GET /api/v1/annonces/{id}' => 'Détail (approuvée, ou propriétaire)',
                'POST /api/v1/annonces' => 'Créer une annonce (Bearer, JSON ou multipart)',
                'PATCH /api/v1/annonces/{id}' => 'Modifier une annonce (Bearer)',
                'POST /api/v1/annonces/{id}/images' => 'Ajouter des photos (multipart images[])',
                'DELETE /api/v1/annonces/{id}' => 'Supprimer une annonce (Bearer)',
                'GET /api/v1/categories' => 'Catégories',
                'GET /api/v1/cities' => 'Villes',
                'GET /api/v1/threads' => 'Mes conversations (Bearer)',
                'POST /api/v1/annonces/{id}/thread' => 'Ouvrir / créer un thread sur une annonce',
                'GET /api/v1/threads/{id}' => 'Messages d’une conversation',
                'POST /api/v1/threads/{id}/messages' => 'Envoyer texte / photo / position',
            ],
        ]);
    }
}
