#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Prépare un dossier (et optionnellement un ZIP) propre pour l’upload production.
 * Exclut : .git, node_modules, tests, docs, .env*, electron, var, etc.
 *
 * Usage (racine du projet) :
 *   php bin/export-prod.php
 *   php bin/export-prod.php --with-vendor
 *   php bin/export-prod.php --zip
 *   php bin/export-prod.php --out=C:/chemin/souk_expat_prod
 *
 * Avant l’export recommandé :
 *   composer install --no-dev --optimize-autoloader
 *   php bin/console asset-map:compile --env=prod
 */

$root = dirname(__DIR__);
chdir($root);

$withVendor = in_array('--with-vendor', $argv, true);
$makeZip = in_array('--zip', $argv, true);
$out = $root . DIRECTORY_SEPARATOR . 'prod-package';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $out = substr($arg, 6);
    }
}

$excludeFile = $root . '/deploy/exclude-prod.txt';
$patterns = [];
if (is_file($excludeFile)) {
    foreach (file($excludeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $patterns[] = rtrim(str_replace('\\', '/', $line), '/');
    }
}

// Toujours exclus même si absents du fichier
$always = [
    '.git', '.phpunit.cache', 'node_modules', 'electron', 'tests', 'docs', 'tools',
    'var', 'prod-package', '.env', '.env.dev', '.env.test', '.env.local',
    '.env.local.example', '.env.prod.example', 'phpunit.dist.xml', 'bin/phpunit',
];
foreach ($always as $p) {
    if (!in_array($p, $patterns, true)) {
        $patterns[] = $p;
    }
}

if (!$withVendor) {
    $patterns[] = 'vendor';
    echo "[INFO] vendor/ exclu — utilisez --with-vendor ou lancez composer sur le serveur.\n";
} else {
    $patterns = array_values(array_filter($patterns, static fn (string $p): bool => $p !== 'vendor'));
    if (!is_dir($root . '/vendor')) {
        fwrite(STDERR, "[ERREUR] vendor/ manquant. Lancez : composer install --no-dev --optimize-autoloader\n");
        exit(1);
    }
}

function isExcluded(string $relative, array $patterns): bool
{
    $relative = str_replace('\\', '/', $relative);
    foreach ($patterns as $pattern) {
        $pattern = ltrim($pattern, '/');
        if ($pattern === $relative) {
            return true;
        }
        // Dossier entier
        if (str_starts_with($relative, $pattern . '/')) {
            return true;
        }
        // Glob simple **
        if (str_contains($pattern, '*')) {
            $regex = '#^' . str_replace(['**', '*'], ['.*', '[^/]*'], preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $relative)) {
                // Exceptions !public/uploads/.gitkeep gérées à part
                return true;
            }
        }
    }

    return false;
}

function shouldKeepUploadPlaceholder(string $relative): bool
{
    $relative = str_replace('\\', '/', $relative);
    return (bool) preg_match('#^public/uploads(/.+)?/\.gitkeep$#', $relative)
        || $relative === 'public/uploads/.gitkeep'
        || $relative === 'public/uploads/.htaccess';
}

echo "=== Export production SoukExpat ===\n";
echo "Source : {$root}\n";
echo "Cible  : {$out}\n\n";

if (is_dir($out)) {
    echo "[INFO] Suppression de l’ancien paquet…\n";
    removeDir($out);
}

if (!mkdir($out, 0755, true) && !is_dir($out)) {
    fwrite(STDERR, "[ERREUR] Impossible de créer {$out}\n");
    exit(1);
}

$copied = 0;
$skipped = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    $full = $fileInfo->getPathname();
    $relative = ltrim(str_replace('\\', '/', substr($full, strlen($root))), '/');

    if ($relative === '' || str_starts_with($relative, 'prod-package')) {
        continue;
    }

    // Garder les .gitkeep des uploads même si public/uploads/** exclu
    if (shouldKeepUploadPlaceholder($relative)) {
        $dest = $out . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        if ($fileInfo->isFile()) {
            copy($full, $dest);
            ++$copied;
        }
        continue;
    }

    // Ne pas emporter les fichiers uploadés (photos, etc.)
    if (str_starts_with($relative, 'public/uploads/') && !shouldKeepUploadPlaceholder($relative)) {
        if ($fileInfo->isFile()) {
            ++$skipped;
            continue;
        }
    }

    if (isExcluded($relative, $patterns)) {
        ++$skipped;
        continue;
    }

    $dest = $out . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if ($fileInfo->isDir()) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        continue;
    }

    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (!copy($full, $dest)) {
        fwrite(STDERR, "[ERREUR] Copie échouée : {$relative}\n");
        exit(1);
    }
    ++$copied;
}

// Dossiers uploads vides
$uploadDirs = [
    'public/uploads',
    'public/uploads/annonces',
    'public/uploads/avatars',
    'public/uploads/categories',
    'public/uploads/sliders',
    'public/uploads/messages',
];
foreach ($uploadDirs as $dir) {
    $path = $out . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $keep = $path . DIRECTORY_SEPARATOR . '.gitkeep';
    if (!is_file($keep)) {
        file_put_contents($keep, '');
    }
}

// Sécurité uploads : toujours emporter .htaccess
$uploadsHtaccessSrc = $root . '/public/uploads/.htaccess';
$uploadsHtaccessDest = $out . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess';
if (is_file($uploadsHtaccessSrc)) {
    copy($uploadsHtaccessSrc, $uploadsHtaccessDest);
}

// Copier le template env uploadable
$envTemplate = $root . '/deploy/env.prod.template';
if (is_file($envTemplate)) {
    $deployDir = $out . DIRECTORY_SEPARATOR . 'deploy';
    if (!is_dir($deployDir)) {
        mkdir($deployDir, 0755, true);
    }
    copy($envTemplate, $deployDir . DIRECTORY_SEPARATOR . 'env.prod.template');
}

// README court dans le paquet
file_put_contents($out . DIRECTORY_SEPARATOR . 'UPLOAD-README.txt', <<<TXT
SoukExpat — paquet production
=============================

1. Uploader TOUT ce dossier sur Hostinger (racine du site / sous-domaine).
2. Document root = dossier public/ (idéal).
3. Remplir deploy/env.prod.template puis :
     php bin/install-env-local.php
4. Si vendor/ absent :
     composer install --no-dev --optimize-autoloader
5. Puis :
     php bin/prepare-prod.php

NE PAS uploader depuis le dossier de développement :
  .git, node_modules, var, tests, .env*
TXT);

echo "[OK] Fichiers copiés : {$copied} (ignorés : {$skipped})\n";

if ($makeZip) {
    if (!class_exists(ZipArchive::class)) {
        fwrite(STDERR, "[ERREUR] Extension ZIP manquante pour --zip\n");
        exit(1);
    }
    $zipPath = rtrim($out, '/\\') . '.zip';
    if (is_file($zipPath)) {
        unlink($zipPath);
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "[ERREUR] Impossible de créer {$zipPath}\n");
        exit(1);
    }
    $zipIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($zipIterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $full = $file->getPathname();
        $local = 'souk_expat/' . ltrim(str_replace('\\', '/', substr($full, strlen($out))), '/');
        $zip->addFile($full, $local);
    }
    $zip->close();
    echo "[OK] ZIP : {$zipPath}\n";
}

$size = dirSize($out);
echo sprintf("[OK] Taille paquet : %.1f Mo\n", $size / 1048576);
echo "\nUploadez le contenu de :\n  {$out}\n";
if (!$withVendor) {
    echo "\nAstuce : pour inclure vendor (sans Composer sur le serveur) :\n  composer install --no-dev --optimize-autoloader\n  php bin/export-prod.php --with-vendor --zip\n";
}

exit(0);

function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function dirSize(string $dir): int
{
    $size = 0;
    if (!is_dir($dir)) {
        return 0;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($items as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }

    return $size;
}
