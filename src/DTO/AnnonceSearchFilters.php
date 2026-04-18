<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

/**
 * Filtres GET communs pour les listes d'annonces (accueil, /annonce, catégorie).
 */
final class AnnonceSearchFilters
{
    public function __construct(
        public readonly ?string $q,
        public readonly ?int $categoryId,
        public readonly ?int $cityId,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $qRaw = $request->query->get('q');
        $q = \is_string($qRaw) ? trim($qRaw) : '';
        $q = $q === '' ? null : $q;

        $categoryId = self::parsePositiveInt($request->query->get('category'));
        $cityId = self::parsePositiveInt($request->query->get('city'));

        return new self($q, $categoryId, $cityId);
    }

    private static function parsePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
