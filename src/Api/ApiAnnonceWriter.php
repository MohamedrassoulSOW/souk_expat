<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Annonce;
use App\Entity\AnnonceImage;
use App\Repository\AnnonceRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Écriture d’annonces API mobile — images en base (BLOB), jamais sur le disque.
 */
final class ApiAnnonceWriter
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly AnnonceRepository $annonceRepository,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function assignUniqueSlug(Annonce $annonce, ?int $ignoreId = null): void
    {
        $baseSlug = $this->slugger->slug($annonce->getTitle())->lower()->toString();
        if ($baseSlug === '') {
            $baseSlug = 'annonce';
        }

        $slug = $baseSlug;
        $i = 1;
        while (($existing = $this->annonceRepository->findOneBy(['slug' => $slug])) instanceof Annonce
            && $existing->getId() !== $ignoreId
        ) {
            $slug = $baseSlug . '-' . $i;
            ++$i;
        }

        $annonce->setSlug($slug);
    }

    /**
     * @param list<UploadedFile> $files
     * @return list<AnnonceImage>
     */
    public function attachUploadedImages(Annonce $annonce, array $files, int $maxFiles = 8): array
    {
        $created = [];
        $count = 0;
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $mime = (string) $file->getMimeType();
            if (!\in_array($mime, self::ALLOWED_MIMES, true)) {
                continue;
            }

            if ($file->getSize() !== false && $file->getSize() > self::MAX_BYTES) {
                continue;
            }

            if ($count >= $maxFiles) {
                break;
            }

            $binary = @file_get_contents($file->getPathname());
            if ($binary === false || $binary === '') {
                continue;
            }

            $created[] = $this->persistBlob($annonce, $binary, $mime);
            ++$count;
        }

        return $created;
    }

    /**
     * Images encodées en base64 dans le JSON mobile.
     * Formats acceptés : data:image/jpeg;base64,... ou base64 brut (+ mimeType optionnel).
     *
     * @param list<mixed> $items
     * @return list<AnnonceImage>
     */
    public function attachBase64Images(Annonce $annonce, array $items, int $maxFiles = 8): array
    {
        $created = [];
        $count = 0;

        foreach ($items as $item) {
            if ($count >= $maxFiles) {
                break;
            }

            $parsed = $this->parseBase64Item($item);
            if ($parsed === null) {
                continue;
            }

            $created[] = $this->persistBlob($annonce, $parsed['binary'], $parsed['mime']);
            ++$count;
        }

        return $created;
    }

    /**
     * @return list<UploadedFile>
     */
    public function collectImageFiles(Request $request): array
    {
        $files = [];
        $bag = $request->files;

        $single = $bag->get('image');
        if ($single instanceof UploadedFile) {
            $files[] = $single;
        }

        $multi = $bag->get('images');
        if ($multi instanceof UploadedFile) {
            $files[] = $multi;
        } elseif (\is_array($multi)) {
            foreach ($multi as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<mixed>
     */
    public function collectBase64FromPayload(array $payload): array
    {
        $items = [];
        if (isset($payload['imagesBase64']) && \is_array($payload['imagesBase64'])) {
            $items = array_merge($items, $payload['imagesBase64']);
        }
        if (isset($payload['imageBase64'])) {
            $items[] = $payload['imageBase64'];
        }

        return $items;
    }

    private function persistBlob(Annonce $annonce, string $binary, string $mime): AnnonceImage
    {
        $image = new AnnonceImage();
        $image->setImadeName(null);
        $image->setContent($binary);
        $image->setMimeType($mime);
        $image->setAnnonce($annonce);
        $annonce->addAnnonceImage($image);

        return $image;
    }

    /**
     * @return array{binary: string, mime: string}|null
     */
    private function parseBase64Item(mixed $item): ?array
    {
        $mime = 'image/jpeg';
        $raw = null;

        if (\is_string($item)) {
            $raw = $item;
        } elseif (\is_array($item)) {
            $raw = $item['data'] ?? $item['base64'] ?? $item['content'] ?? null;
            if (isset($item['mimeType']) && \is_string($item['mimeType'])) {
                $mime = $item['mimeType'];
            }
        }

        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        if (preg_match('#^data:(image/(?:jpeg|png|webp));base64,(.+)$#i', $raw, $m)) {
            $mime = strtolower($m[1]);
            $raw = $m[2];
        }

        if (!\in_array($mime, self::ALLOWED_MIMES, true)) {
            return null;
        }

        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '' || \strlen($binary) > self::MAX_BYTES) {
            return null;
        }

        return ['binary' => $binary, 'mime' => $mime];
    }
}
