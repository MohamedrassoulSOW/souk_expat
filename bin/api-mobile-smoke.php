<?php
/**
 * Smoke test API mobile /api/v1 (local).
 * Usage: php bin/api-mobile-smoke.php [baseUrl]
 */
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8001', '/');
$results = [];
$ok = 0;
$fail = 0;

function req(string $method, string $url, ?array $json = null, ?string $token = null, ?array $multipart = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HEADER => true,
    ]);

    if ($multipart !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
    } elseif ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($errno) {
        return ['status' => 0, 'body' => null, 'error' => $err];
    }

    $bodyRaw = substr((string) $raw, $headerSize);
    $body = json_decode($bodyRaw, true);

    return ['status' => $status, 'body' => $body, 'raw' => $bodyRaw];
}

function check(string $name, bool $pass, string $detail = ''): void
{
    global $results, $ok, $fail;
    if ($pass) {
        ++$ok;
        $results[] = "OK   $name" . ($detail !== '' ? " — $detail" : '');
    } else {
        ++$fail;
        $results[] = "FAIL $name" . ($detail !== '' ? " — $detail" : '');
    }
}

// 1. Index
$r = req('GET', "$base/api/v1");
check('GET /api/v1', $r['status'] === 200 && ($r['body']['name'] ?? '') !== '', 'HTTP ' . $r['status']);
$endpoints = $r['body']['endpoints'] ?? [];
check('Index lists endpoints', \is_array($endpoints) && \count($endpoints) >= 10, (string) \count($endpoints));

// 2. CORS preflight
$ch = curl_init("$base/api/v1/annonces");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'OPTIONS',
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false,
    CURLOPT_HTTPHEADER => [
        'Origin: http://localhost',
        'Access-Control-Request-Method: GET',
    ],
]);
$raw = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
check('OPTIONS CORS', $status === 204 || $status === 200, 'HTTP ' . $status);
check('CORS Allow-Origin', is_string($raw) && str_contains($raw, 'Access-Control-Allow-Origin'), '');

// 3. Public catalog
$r = req('GET', "$base/api/v1/categories");
$cats = $r['body']['items'] ?? [];
check('GET /categories', $r['status'] === 200 && \is_array($cats), 'HTTP ' . $r['status'] . ' count=' . (\is_array($cats) ? \count($cats) : 0));

$r = req('GET', "$base/api/v1/cities");
$cities = $r['body']['items'] ?? [];
check('GET /cities', $r['status'] === 200 && \is_array($cities), 'HTTP ' . $r['status'] . ' count=' . (\is_array($cities) ? \count($cities) : 0));

$r = req('GET', "$base/api/v1/annonces?limit=3");
$items = $r['body']['items'] ?? null;
check('GET /annonces', $r['status'] === 200 && \is_array($items), 'HTTP ' . $r['status'] . ' total=' . ($r['body']['total'] ?? '?'));

$firstAnnonceId = null;
if (\is_array($items) && isset($items[0]['id'])) {
    $firstAnnonceId = (int) $items[0]['id'];
    $r = req('GET', "$base/api/v1/annonces/$firstAnnonceId");
    check('GET /annonces/{id}', $r['status'] === 200 && isset($r['body']['item']['id']), 'HTTP ' . $r['status']);
} else {
    check('GET /annonces/{id}', true, 'skipped (no approved annonces)');
}

// 4. Auth required without token
$r = req('GET', "$base/api/v1/me");
check('GET /me without token → 401', $r['status'] === 401, 'HTTP ' . $r['status']);

$r = req('GET', "$base/api/v1/threads");
check('GET /threads without token → 401', $r['status'] === 401, 'HTTP ' . $r['status']);

$r = req('POST', "$base/api/v1/annonces", ['title' => 'x']);
check('POST /annonces without token → 401', $r['status'] === 401, 'HTTP ' . $r['status']);

// 5. Login
$email = 'utilisateur1@souk-demo.local';
$password = 'DemoSouk2026!';
$r = req('POST', "$base/api/v1/auth/login", ['email' => $email, 'password' => $password]);
$token = $r['body']['accessToken'] ?? null;
check('POST /auth/login', $r['status'] === 200 && \is_string($token) && $token !== '', 'HTTP ' . $r['status']);

$r = req('POST', "$base/api/v1/auth/login", ['email' => $email, 'password' => 'wrong-password']);
check('Login bad password → 401/422', \in_array($r['status'], [401, 422], true), 'HTTP ' . $r['status']);

if (!\is_string($token) || $token === '') {
    echo implode("\n", $results) . "\n";
    echo "\nSUMMARY: $ok OK, $fail FAIL (stopped: no token)\n";
    exit(1);
}

// 6. Me
$r = req('GET', "$base/api/v1/me", null, $token);
check('GET /me', $r['status'] === 200 && ($r['body']['user']['email'] ?? '') === $email, 'HTTP ' . $r['status']);

$r = req('GET', "$base/api/v1/me/annonces", null, $token);
check('GET /me/annonces', $r['status'] === 200 && isset($r['body']['items']), 'HTTP ' . $r['status'] . ' total=' . ($r['body']['total'] ?? '?'));

$r = req('GET', "$base/api/v1/threads", null, $token);
check('GET /threads', $r['status'] === 200 && isset($r['body']['items']), 'HTTP ' . $r['status'] . ' count=' . (\is_array($r['body']['items'] ?? null) ? \count($r['body']['items']) : 0));

// 7. Create / update / delete annonce (cleanup)
$catId = $cats[0]['id'] ?? null;
$cityId = $cities[0]['id'] ?? null;
$createdId = null;

if ($catId && $cityId) {
    $r = req('POST', "$base/api/v1/annonces", [
        'title' => 'Smoke API Mobile ' . date('H:i:s'),
        'description' => 'Annonce temporaire créée par le smoke test API mobile.',
        'price' => 999,
        'categoryId' => $catId,
        'cityId' => $cityId,
        'phone' => '0611223344',
    ], $token);
    $createdId = $r['body']['item']['id'] ?? null;
    check(
        'POST /annonces create',
        $r['status'] === 201 && $createdId && ($r['body']['item']['status'] ?? '') === 'pending',
        'HTTP ' . $r['status'] . ' id=' . ($createdId ?? '?')
    );

    if ($createdId) {
        $r = req('GET', "$base/api/v1/annonces/$createdId", null, $token);
        check('GET own pending annonce', $r['status'] === 200 && ($r['body']['item']['phone'] ?? null) === '0611223344', 'HTTP ' . $r['status']);

        $r = req('PATCH', "$base/api/v1/annonces/$createdId", [
            'title' => 'Smoke API Mobile Updated',
            'price' => 1200,
        ], $token);
        check('PATCH /annonces/{id}', $r['status'] === 200 && (float) ($r['body']['item']['price'] ?? 0) === 1200.0, 'HTTP ' . $r['status']);

        $r = req('DELETE', "$base/api/v1/annonces/$createdId", null, $token);
        check('DELETE /annonces/{id}', $r['status'] === 200 && ($r['body']['ok'] ?? false) === true, 'HTTP ' . $r['status']);
    }
} else {
    check('POST /annonces create', false, 'missing category/city seed data');
}

// 8. Open thread on approved annonce (if any other seller)
if ($firstAnnonceId) {
    $r = req('POST', "$base/api/v1/annonces/$firstAnnonceId/thread", [], $token);
    // 201/200 ok, 403 if own listing, 404 if gone
    check(
        'POST /annonces/{id}/thread',
        \in_array($r['status'], [200, 201, 403], true),
        'HTTP ' . $r['status'] . ' ' . ($r['body']['message'] ?? $r['body']['error'] ?? '')
    );

    if (\in_array($r['status'], [200, 201], true) && isset($r['body']['item']['id'])) {
        $threadId = (int) $r['body']['item']['id'];
        $r = req('POST', "$base/api/v1/threads/$threadId/messages", [
            'content' => 'Smoke test message ' . date('H:i:s'),
        ], $token);
        check('POST /threads/{id}/messages', $r['status'] === 201 && ($r['body']['item']['kind'] ?? '') === 'text', 'HTTP ' . $r['status']);

        $r = req('GET', "$base/api/v1/threads/$threadId", null, $token);
        check('GET /threads/{id}', $r['status'] === 200 && isset($r['body']['item']['messages']), 'HTTP ' . $r['status']);
    }
} else {
    check('POST /annonces/{id}/thread', true, 'skipped (no public annonce)');
}

echo implode("\n", $results) . "\n";
echo "\nSUMMARY: $ok OK, $fail FAIL\n";
exit($fail > 0 ? 1 : 0);
