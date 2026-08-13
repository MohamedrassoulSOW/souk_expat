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
    'public/vendor/bootstrap/css/bootstrap.min.css',
    'public/vendor/bootstrap/js/bootstrap.bundle.min.js',
    'public/vendor/bootstrap-icons/css/bootstrap-icons.min.css',
    'public/vendor/tom-select/tom-select.complete.min.js',
    'public/vendor/tom-select/tom-select.bootstrap5.min.css',
    'public/uploads/.htaccess',
];
$missingIcons = false;
foreach ($pwaFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        if (str_contains($file, '/icons/')) {
            $missingIcons = true;
            continue;
        }
        if ($file === 'public/uploads/.htaccess') {
            $src = $root . '/public/uploads/.htaccess';
            // already missing — try recreate from known template next
            $htaccess = <<<'HTA'
# Empêche l’exécution de scripts dans les uploads (Apache / Hostinger)
<IfModule mod_authz_core.c>
    <FilesMatch "\.(?i:php|phtml|phar|php\d+|cgi|pl|py|asp|aspx|sh)$">
        Require all denied
    </FilesMatch>
</IfModule>
<IfModule !mod_authz_core.c>
    <FilesMatch "\.(?i:php|phtml|phar|php\d+|cgi|pl|py|asp|aspx|sh)$">
        Order allow,deny
        Deny from all
    </FilesMatch>
</IfModule>
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
Options -Indexes -ExecCGI
HTA;
            if (!is_dir(dirname($src))) {
                mkdir(dirname($src), 0775, true);
            }
            file_put_contents($src, $htaccess);
            echo "[OK] public/uploads/.htaccess (créé)\n";
            continue;
        }
        fail("Fichier PWA/vendor manquant : {$file}");
    }
    echo "[OK] {$file}\n";
}
if ($missingIcons) {
    echo "[INFO] Icônes PWA manquantes — génération…\n";
    if (run('php bin/generate-pwa-icons.php') !== 0) {
        fail('Impossible de générer les icônes PWA (logo + extension GD requis).');
    }
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
run('php bin/console debug:router admin_analytics --env=prod');
run('php bin/console debug:router admin_slider_index --env=prod');
run('php bin/console doctrine:migrations:status --env=prod');

echo <<<TXT

=== Terminé ===
Checklist manuelle :
  1. APP_ENV=prod et APP_DEBUG=0
  2. APP_SECRET unique (pas celui du .env de dev)
  3. JWT_SECRET dédié (≥ 32 caractères, ≠ APP_SECRET)
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
       php bin/health-check.php https://votre-domaine
       curl -I https://votre-domaine/
       curl -I https://votre-domaine/api/v1
       curl -I https://votre-domaine/manifest.webmanifest
       curl -I https://votre-domaine/sw.js
 13. Cron quotidien (messages > 30 jours) :
       15 3 * * * cd /chemin/vers/projet && php bin/console app:messages:purge-expired --env=prod
 14. Valider : slider image+vidéo, multi-photos annonces, export analytics

TXT;
