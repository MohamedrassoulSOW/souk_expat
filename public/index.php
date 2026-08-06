<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $env = $context['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? 'prod');
    $debug = (bool) ($context['APP_DEBUG'] ?? ($_SERVER['APP_DEBUG'] ?? false));

    // Sur les hébergements mutualisés, il arrive que APP_ENV retombe sur "dev"
    // même si les dépendances dev (debug bundle) n'ont pas été installées.
    // Dans ce cas, forcer "prod" évite le crash (DebugBundle manquant).
    if ($env === 'dev' && !class_exists(\Symfony\Bundle\DebugBundle\DebugBundle::class)) {
        $env = 'prod';
        $debug = false;
    }

    return new Kernel($env, $debug);
};
