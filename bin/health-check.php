<?php
declare(strict_types=1);

/**
 * Smoke check site + API mobile.
 * Usage: php bin/health-check.php [baseUrl]
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8001', '/');
$ok = 0;
$fail = 0;
$lines = [];

function check(string $name, bool $pass, string $detail = ''): void
{
    global $ok, $fail, $lines;
    if ($pass) {
        ++$ok;
        $lines[] = "OK   $name" . ($detail !== '' ? " — $detail" : '');
    } else {
        ++$fail;
        $lines[] = "FAIL $name" . ($detail !== '' ? " — $detail" : '');
    }
}

function http(string $method, string $url, ?array $json = null, ?string $token = null, bool $follow = true): array
{
    $ch = curl_init($url);
    $headers = ['Accept: text/html,application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($errno) {
        return ['status' => 0, 'body' => '', 'error' => $err];
    }
    $body = substr((string) $raw, $headerSize);

    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
}

// —— Site web ——
$pages = [
    'Accueil' => '/',
    'Annonces' => '/annonce/',
    'Contact' => '/contact',
    'À propos' => '/about',
    'Login' => '/login',
    'API index' => '/api/v1',
    'PWA manifest' => '/manifest.webmanifest',
    'PWA service worker' => '/sw.js',
    'PWA offline' => '/offline.html',
    'PWA icon 192' => '/icons/icon-192.png',
];

foreach ($pages as $label => $path) {
    $r = http('GET', $base . $path);
    $pass = $r['status'] >= 200 && $r['status'] < 400;
    check("WEB $label", $pass, 'HTTP ' . $r['status'] . ($r['error'] ?? ''));
}

// —— API publique ——
$r = http('GET', "$base/api/v1");
$endpoints = $r['json']['endpoints'] ?? [];
check('API /api/v1', $r['status'] === 200 && ($r['json']['name'] ?? '') !== '', 'HTTP ' . $r['status']);
check('API endpoints list', is_array($endpoints) && count($endpoints) >= 10, (string) count($endpoints));

$r = http('GET', "$base/api/v1/categories");
$cats = $r['json']['items'] ?? [];
check('API categories', $r['status'] === 200 && is_array($cats) && count($cats) > 0, 'count=' . (is_array($cats) ? count($cats) : 0));

$r = http('GET', "$base/api/v1/cities");
$cities = $r['json']['items'] ?? [];
check('API cities', $r['status'] === 200 && is_array($cities) && count($cities) > 0, 'count=' . (is_array($cities) ? count($cities) : 0));

$r = http('GET', "$base/api/v1/annonces?limit=3");
$items = $r['json']['items'] ?? null;
check('API annonces list', $r['status'] === 200 && is_array($items), 'total=' . ($r['json']['total'] ?? '?'));

$hasWaFields = is_array($items) && isset($items[0]) && array_key_exists('hasWhatsapp', $items[0]) && array_key_exists('whatsappUrl', $items[0]);
check('API WhatsApp fields', $hasWaFields || (is_array($items) && count($items) === 0), $hasWaFields ? 'present' : 'empty list');

$firstId = is_array($items) && isset($items[0]['id']) ? (int) $items[0]['id'] : null;
if ($firstId) {
    $r = http('GET', "$base/api/v1/annonces/$firstId");
    check('API annonce detail', $r['status'] === 200 && isset($r['json']['item']['id']), 'HTTP ' . $r['status']);
    $item = $r['json']['item'] ?? [];
    check('API detail WhatsApp', array_key_exists('whatsappPhone', $item) && array_key_exists('whatsappUrl', $item), '');
} else {
    check('API annonce detail', true, 'skipped');
    check('API detail WhatsApp', true, 'skipped');
}

// Auth guard
$r = http('GET', "$base/api/v1/me");
check('API /me sans token → 401', $r['status'] === 401, 'HTTP ' . $r['status']);

$r = http('GET', "$base/api/v1/threads");
check('API /threads sans token → 401', $r['status'] === 401, 'HTTP ' . $r['status']);

// Login
$r = http('POST', "$base/api/v1/auth/login", [
    'email' => 'utilisateur1@souk-demo.local',
    'password' => 'DemoSouk2026!',
]);
$token = $r['json']['accessToken'] ?? null;
check('API login', $r['status'] === 200 && is_string($token) && $token !== '', 'HTTP ' . $r['status']);

if (is_string($token) && $token !== '') {
    $r = http('GET', "$base/api/v1/me", null, $token);
    check('API /me', $r['status'] === 200 && ($r['json']['user']['email'] ?? '') === 'utilisateur1@souk-demo.local', 'HTTP ' . $r['status']);

    $r = http('GET', "$base/api/v1/me/annonces", null, $token);
    check('API /me/annonces', $r['status'] === 200 && isset($r['json']['items']), 'total=' . ($r['json']['total'] ?? '?'));

    $r = http('GET', "$base/api/v1/threads", null, $token);
    check('API /threads', $r['status'] === 200 && isset($r['json']['items']), 'count=' . (is_array($r['json']['items'] ?? null) ? count($r['json']['items']) : 0));
} else {
    check('API /me', false, 'no token');
    check('API /me/annonces', false, 'no token');
    check('API /threads', false, 'no token');
}

// Console sanity (on force prod pour éviter les dépendances dev manquantes)
passthru('php bin/console --env=prod lint:container --no-debug 2>&1', $containerCode);
check('lint:container', $containerCode === 0, 'exit=' . $containerCode);

echo implode("\n", $lines) . "\n\n";
echo "SUMMARY: $ok OK, $fail FAIL\n";
exit($fail > 0 ? 1 : 0);
