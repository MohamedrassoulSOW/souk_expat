#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Crée .env.local sur le serveur quand l’upload FTP refuse les fichiers .env*
 *
 * Usage (racine du projet) :
 *   php bin/install-env-local.php
 *   php bin/install-env-local.php deploy/env.prod.template
 *   php bin/install-env-local.php /chemin/vers/env.local.txt
 *
 * Ne jamais committer le .env.local généré avec de vrais secrets.
 */

$root = dirname(__DIR__);
chdir($root);

$source = $argv[1] ?? ($root . '/deploy/env.prod.template');
$target = $root . '/.env.local';

if (!is_file($source)) {
    fwrite(STDERR, "[ERREUR] Fichier source introuvable : {$source}\n");
    fwrite(STDERR, "Uploadez deploy/env.prod.template (rempli) puis relancez.\n");
    exit(1);
}

$content = file_get_contents($source);
if ($content === false || trim($content) === '') {
    fwrite(STDERR, "[ERREUR] Impossible de lire ou fichier vide : {$source}\n");
    exit(1);
}

// Retirer les lignes de commentaires d’aide trop verbeuses en tête (optionnel : on garde tout)
if (is_file($target)) {
    $backup = $target . '.bak.' . date('YmdHis');
    if (!rename($target, $backup)) {
        fwrite(STDERR, "[ERREUR] Impossible de sauvegarder l’ancien .env.local\n");
        exit(1);
    }
    echo "[OK] Ancien .env.local → {$backup}\n";
}

if (file_put_contents($target, $content) === false) {
    fwrite(STDERR, "[ERREUR] Impossible d’écrire {$target} (droits ?)\n");
    exit(1);
}

@chmod($target, 0600);

echo "[OK] .env.local créé depuis {$source}\n";

$required = ['APP_ENV', 'APP_SECRET', 'DATABASE_URL', 'DEFAULT_URI', 'MAILER_DSN', 'JWT_SECRET'];
$missing = [];
foreach ($required as $key) {
    if (!preg_match('/^' . preg_quote($key, '/') . '=(.+)$/m', $content, $m)) {
        $missing[] = $key;
        continue;
    }
    $val = trim($m[1], " \t\"'");
    if ($val === '' || str_contains($val, 'USER:PASSWORD') || str_contains($val, 'ChangeMe')) {
        if (in_array($key, ['APP_SECRET', 'DATABASE_URL', 'MAILER_DSN', 'JWT_SECRET'], true)) {
            $missing[] = $key . ' (vide ou placeholder)';
        }
    }
}

if ($missing !== []) {
    echo "\n[ATTENTION] À compléter dans .env.local :\n";
    foreach ($missing as $item) {
        echo "  - {$item}\n";
    }
    echo "Éditez le fichier (SSH : nano .env.local) puis : php bin/prepare-prod.php\n";
    exit(2);
}

echo "\nVariables principales OK. Ensuite :\n";
echo "  php bin/prepare-prod.php\n";
exit(0);
