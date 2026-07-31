#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Prépare l’application pour la production.
 *
 * Usage (sur le serveur, depuis la racine du projet) :
 *   php bin/prepare-prod.php
 *
 * Prérequis : .env.local (ou variables d’env) avec APP_ENV=prod, APP_SECRET, DATABASE_URL, MAILER_DSN, DEFAULT_URI.
 */

$root = dirname(__DIR__);
chdir($root);

function run(string $cmd): int
{
    echo "\n>>> {$cmd}\n";
    passthru($cmd, $code);

    return (int) $code;
}

function fail(string $message): never
{
    fwrite(STDERR, "\n[ERREUR] {$message}\n");
    exit(1);
}

echo "=== SoukExpat — préparation production ===\n";

if (!is_file($root . '/vendor/autoload.php')) {
    fail('vendor/ manquant. Lancez d’abord : composer install --no-dev --optimize-autoloader');
}

$envFile = $root . '/.env.local';
if (!is_file($envFile) && getenv('APP_ENV') !== 'prod') {
    echo "[INFO] Pas de .env.local détecté. Assurez-vous que APP_ENV=prod est défini sur l’hébergeur.\n";
}

$dirs = [
    'var/cache',
    'var/log',
    'var/share',
    'public/uploads',
    'public/uploads/annonces',
    'public/uploads/avatars',
    'public/uploads/categories',
    'public/uploads/sliders',
    'public/uploads/messages',
];

foreach ($dirs as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        fail("Impossible de créer {$dir}");
    }
    echo "[OK] {$dir}\n";
}

$pwaFiles = [
    'public/manifest.webmanifest',
    'public/sw.js',
    'public/offline.html',
    'public/icons/icon-192.png',
    'public/icons/icon-512.png',
    'public/icons/apple-touch-icon.png',
];
foreach ($pwaFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        fail("Fichier PWA manquant : {$file}");
    }
    echo "[OK] {$file}\n";
}

$steps = [
    'php bin/console cache:clear --env=prod --no-warmup',
    'php bin/console assets:install public --env=prod',
    'php bin/console importmap:install --env=prod',
    'php bin/console asset-map:compile --env=prod',
    'php bin/console doctrine:migrations:migrate --no-interaction --env=prod',
    'php bin/console cache:warmup --env=prod',
];

foreach ($steps as $step) {
    $code = run($step);
    if ($code !== 0) {
        fail("Échec de la commande (code {$code}) : {$step}");
    }
}

echo "\n=== Vérifications ===\n";
run('php bin/console about --env=prod');
run('php bin/console debug:router api_v1_index --env=prod');

echo <<<TXT

=== Terminé ===
Checklist manuelle :
  1. APP_ENV=prod et APP_DEBUG=0
  2. APP_SECRET unique (pas celui du .env de dev)
  3. JWT_SECRET dédié (≥ 32 caractères)
  4. DATABASE_URL pointe vers la base prod
  5. MAILER_DSN = SMTP réel (pas Mailtrap) + test :
       php bin/console app:mailer:smoke-test votre@email.com --env=prod
  6. DEFAULT_URI = https://votre-domaine (sans / final)
  7. Document root Apache/Nginx = dossier public/
  8. HTTPS obligatoire (PWA + cookies sécurisés)
  9. Droits écriture : var/ et public/uploads/
 10. Admin → Paramètres : WhatsApp du site (bouton flottant)
 11. Créer un compte admin si besoin :
       php bin/console app:user:create-editor --env=prod
 12. Smoke tests :
       curl -I https://votre-domaine/
       curl -I https://votre-domaine/api/v1
       curl -I https://votre-domaine/manifest.webmanifest
       curl -I https://votre-domaine/sw.js

TXT;
