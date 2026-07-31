<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\Annonce;
use App\Entity\AnnonceImage;
use App\Repository\AnnonceRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Helpers partagés pour l’écriture d’annonces via l’API mobile.
 */
final class ApiAnnonceWriter
{
    public function __construct(
        private readonly AnnonceRepository $annonceRepository,
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
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
        $dir = $this->projectDir . '/public/uploads/annonces';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $created = [];
        $count = 0;
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $mime = (string) $file->getMimeType();
            if (!\in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }

            if ($file->getSize() !== false && $file->getSize() > 4 * 1024 * 1024) {
                continue;
            }

            if ($count >= $maxFiles) {
                break;
            }

            $ext = $file->guessExtension() ?: 'jpg';
            $filename = uniqid('ann_', true) . '.' . $ext;
            $file->move($dir, $filename);

            $image = new AnnonceImage();
            $image->setImadeName($filename);
            $image->setAnnonce($annonce);
            $annonce->addAnnonceImage($image);
            $created[] = $image;
            ++$count;
        }

        return $created;
    }

    /**
     * @return list<UploadedFile>
     */
    public function collectImageFiles(\Symfony\Component\HttpFoundation\Request $request): array
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
}
