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
];

foreach ($dirs as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        fail("Impossible de créer {$dir}");
    }
    echo "[OK] {$dir}\n";
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
  3. DATABASE_URL pointe vers la base prod
  4. MAILER_DSN = SMTP réel (pas Mailtrap) + test :
       php bin/console app:mailer:smoke-test votre@email.com --env=prod
  5. DEFAULT_URI = https://votre-domaine (sans / final)
  6. Document root Apache/Nginx = dossier public/
  7. Droits écriture : var/ et public/uploads/
  8. Créer un compte admin si besoin :
       php bin/console app:user:create-editor --env=prod

TXT;
