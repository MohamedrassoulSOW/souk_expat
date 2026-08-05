<?php
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8088', '/');
$projectDir = dirname(__DIR__);

function req(string $method, string $url, ?array $json = null, ?string $token = null): array
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
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, json_decode((string) $body, true), (string) $body];
}

[$c, $login] = req('POST', "$base/api/v1/auth/login", [
    'email' => 'utilisateur1@souk-demo.local',
    'password' => 'DemoSouk2026!',
]);
if ($c !== 200 || empty($login['accessToken'])) {
    fwrite(STDERR, "LOGIN FAIL HTTP $c\n");
    exit(1);
}
$token = $login['accessToken'];

[, $cats] = req('GET', "$base/api/v1/categories");
[, $cities] = req('GET', "$base/api/v1/cities");
$catId = $cats['items'][0]['id'] ?? null;
$cityId = $cities['items'][0]['id'] ?? null;

$pngB64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
$uploadDir = $projectDir . '/public/uploads/annonces';
$before = is_dir($uploadDir) ? count(glob($uploadDir . '/*') ?: []) : 0;

[$c, $created, $raw] = req('POST', "$base/api/v1/annonces", [
    'title' => 'Blob DB Test ' . date('H:i:s'),
    'description' => 'Image stockee uniquement en base de donnees pour mobile.',
    'price' => 50,
    'categoryId' => $catId,
    'cityId' => $cityId,
    'imagesBase64' => ['data:image/png;base64,' . $pngB64],
], $token);

echo "CREATE HTTP=$c\n";
if ($c !== 201) {
    fwrite(STDERR, $raw . "\n");
    exit(1);
}

$id = $created['item']['id'];
$images = $created['item']['images'] ?? [];
echo "id=$id images=" . json_encode($images) . "\n";

$after = is_dir($uploadDir) ? count(glob($uploadDir . '/*') ?: []) : 0;
echo "disk_files before=$before after=$after delta=" . ($after - $before) . "\n";

$url = $images[0] ?? '';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
$bin = curl_exec($ch);
$mc = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

$isPng = is_string($bin) && str_starts_with($bin, "\x89PNG");
echo "MEDIA HTTP=$mc ctype=$ct bytes=" . strlen((string) $bin) . ' png=' . ($isPng ? 'yes' : 'no') . "\n";
echo 'media_is_api=' . (str_contains($url, '/api/v1/media/annonce-images/') ? 'yes' : 'no') . "\n";

req('DELETE', "$base/api/v1/annonces/$id", null, $token);
echo "CLEANUP ok\n";

$pass = $c === 201 && ($after - $before) === 0 && $mc === 200 && $isPng && str_contains($url, '/api/v1/media/');
echo $pass ? "PASS\n" : "FAIL\n";
exit($pass ? 0 : 1);
