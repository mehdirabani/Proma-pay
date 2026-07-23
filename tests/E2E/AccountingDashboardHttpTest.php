<?php

declare(strict_types=1);

$baseUrl = rtrim((string) getenv('PROMA_QA_BASE_URL'), '/');
$username = (string) getenv('PROMA_QA_ADMIN_USER');
$password = (string) getenv('PROMA_QA_ADMIN_PASSWORD');
if ($baseUrl === '' || $username === '' || $password === '') {
    fwrite(STDERR, "PROMA_QA_BASE_URL and admin credentials are required.\n");
    exit(2);
}
$cookie = tempnam(sys_get_temp_dir(), 'proma-acc-http-');
$request = static function ($route, $cookie, array $post = null) use ($baseUrl) {
    $ch = curl_init($baseUrl . '/index.php?route=' . rawurlencode($route));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HEADER => true,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $startedAt = microtime(true);
    $raw = curl_exec($ch);
    $durationMs = (microtime(true) - $startedAt) * 1000;
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException($error);
    return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize), 'duration_ms' => $durationMs, 'url' => $effectiveUrl];
};

try {
    $loginPage = $request('auth/login', $cookie);
    if (!preg_match('/name=["\']_csrf["\'][^>]*value=["\']([^"\']+)/i', $loginPage['body'], $match)) {
        throw new RuntimeException('Login CSRF token was not rendered.');
    }
    $request('auth/login', $cookie, ['_csrf' => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'identifier' => $username, 'password' => $password]);

    $iterations = max(5, min(500, (int) (getenv('PROMA_QA_ACCOUNTING_ITERATIONS') ?: 20)));
    $durations = [];
    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        $response = $request('plugin/accounting/dashboard', $cookie);
        if ($response['status'] !== 200 || stripos($response['body'], 'SQLSTATE[') !== false || stripos($response['body'], 'Fatal error') !== false) {
            throw new RuntimeException('Accounting dashboard HTTP regression at iteration ' . $iteration . ', status ' . $response['status']);
        }
        if (strpos(urldecode($response['url']), 'route=plugin/accounting/dashboard') === false) {
            throw new RuntimeException('Accounting dashboard redirected away from the tested route at iteration ' . $iteration . '.');
        }
        if (!preg_match('/X-Request-Id:\s*[a-f0-9]{20,}/i', $response['headers'])) {
            throw new RuntimeException('Accounting dashboard response has no request ID.');
        }
        $durations[] = $response['duration_ms'];
    }
    sort($durations, SORT_NUMERIC);
    $p95 = $durations[(int) floor((count($durations) - 1) * 0.95)];
    if ($p95 > 1000) {
        throw new RuntimeException('Accounting dashboard HTTP p95 exceeded 1000 ms: ' . round($p95, 2));
    }
    $p50Index = (int) floor((count($durations) - 1) * 0.50);
    echo json_encode(['status' => 'PASSED', 'iterations' => count($durations), 'p50_ms' => round($durations[$p50Index], 2), 'p95_ms' => round($p95, 2), 'max_ms' => round(max($durations), 2)], JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    if (is_file($cookie)) unlink($cookie);
}
