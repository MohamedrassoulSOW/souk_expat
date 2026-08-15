<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stocke une image après contrôle du contenu réel (finfo + getimagesize),
 * jamais d’après l’extension ou le MIME déclaré par le client.
 */
final class SafeImageUploader
{
    public const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * @param list<string>|null $allowedMimes
     *
     * @throws FileException
     */
    public function store(
        UploadedFile $file,
        string $absoluteDir,
        int $maxBytes = 4_194_304,
        ?array $allowedMimes = null,
        string $filenamePrefix = '',
    ): string {
        if (!$file->isValid()) {
            throw new FileException('Fichier invalide.');
        }

        $size = $file->getSize();
        if ($size === false || $size > $maxBytes) {
            throw new FileException('Fichier trop volumineux.');
        }

        $mime = $this->detectMime($file);
        $allowed = $allowedMimes ?? array_keys(self::MIME_TO_EXT);
        if (!\in_array($mime, $allowed, true) || !isset(self::MIME_TO_EXT[$mime])) {
            throw new FileException('Type de fichier non autorisé.');
        }

        $info = @getimagesize($file->getPathname());
        if ($info === false) {
            throw new FileException('Image illisible.');
        }

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new FileException('Impossible de créer le dossier d’upload.');
        }

        $filename = $filenamePrefix.bin2hex(random_bytes(16)).'.'.self::MIME_TO_EXT[$mime];
        $file->move($absoluteDir, $filename);

        return $filename;
    }

    private function detectMime(UploadedFile $file): string
    {
        $path = $file->getPathname();
        if (\function_exists('finfo_open')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($path);
            if (\is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        $info = @getimagesize($path);

        return \is_array($info) && isset($info['mime']) ? (string) $info['mime'] : '';
    }
}
