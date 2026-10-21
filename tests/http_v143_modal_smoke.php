<?php

declare(strict_types=1);

if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP cURL extension is required.\n");
    exit(2);
}

$baseUrl = rtrim((string) getenv('PROMA_QA_BASE_URL'), '/');
$dsn = (string) getenv('PROMA_TEST_DB_DSN');
$dbUser = (string) (getenv('PROMA_TEST_DB_USER') ?: '');
$dbPassword = (string) (getenv('PROMA_TEST_DB_PASSWORD') ?: '');
$adminUser = (string) getenv('PROMA_QA_ADMIN_USER');
$adminPassword = (string) getenv('PROMA_QA_ADMIN_PASSWORD');
if ($baseUrl === '' || $dsn === '' || $adminUser === '' || $adminPassword === '') {
    fwrite(STDERR, "QA URL, database, and admin credentials are required.\n");
    exit(2);
}

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$request = static function (string $url, string $cookieFile, ?array $post = null): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'PromaPay-V1.4.3-Modal-QA',
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    if ($body === false) {
        throw new RuntimeException('HTTP request failed: ' . $error);
    }
    return ['status' => $status, 'body' => (string) $body, 'url' => $effectiveUrl];
};
$routeUrl = static fn(string $route): string => $baseUrl . '/index.php?route=' . rawurlencode($route);

$pdo = new PDO($dsn, $dbUser, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$contractId = (int) $pdo->query(
    "SELECT id FROM contracts WHERE status = 'active' ORDER BY id DESC LIMIT 1"
)->fetchColumn();
$assert($contractId > 0, 'An active contract fixture is required for modal QA.');

$cookieFile = tempnam(sys_get_temp_dir(), 'proma-modal-v143-');
try {
    $loginPage = $request($routeUrl('auth/login'), $cookieFile);
    $assert($loginPage['status'] === 200, 'Login page returned HTTP ' . $loginPage['status'] . '.');
    $matched = preg_match('/name=["\']_csrf["\'][^>]*value=["\']([^"\']+)["\']/i', $loginPage['body'], $csrfMatch);
    if ($matched !== 1) {
        $matched = preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']_csrf["\']/i', $loginPage['body'], $csrfMatch);
    }
    $assert($matched === 1, 'CSRF token is missing from the login page.');

    $login = $request($routeUrl('auth/login'), $cookieFile, [
        '_csrf' => html_entity_decode((string) $csrfMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'identifier' => $adminUser,
        'password' => $adminPassword,
    ]);
    $assert($login['status'] === 200, 'Admin login returned HTTP ' . $login['status'] . '.');
    $assert(strpos($login['url'], 'route=auth') === false, 'Admin authentication did not leave the login route.');

    $detail = $request($routeUrl('contracts/show/' . $contractId), $cookieFile);
    $assert($detail['status'] === 200, 'Contract detail returned HTTP ' . $detail['status'] . '.');
    foreach ([
        'id="add-contract-installment"',
        'data-disable-on-submit',
        '<span class="proma-form-label">توضیح قسط برای مشتری</span>',
        '<span class="proma-form-label">یادداشت داخلی</span>',
    ] as $requiredMarkup) {
        $assert(strpos($detail['body'], $requiredMarkup) !== false, 'Custom installment modal is missing: ' . $requiredMarkup);
    }
    $assert(
        preg_match('/<form[^>]+action="[^"]*route=installments(?:%2F|\/)store[^"]*"[^>]+data-disable-on-submit/i', $detail['body']) === 1,
        'Custom installment form action or duplicate-submit guard is missing.'
    );
    $assert(!preg_match('/Fatal error|Uncaught|Parse error|SQLSTATE\[|Warning:\s/i', $detail['body']), 'Contract detail exposed a runtime failure.');

    $forms = $request($baseUrl . '/assets/css/components/forms.css', $cookieFile);
    $script = $request($baseUrl . '/assets/js/app.js', $cookieFile);
    $assert($forms['status'] === 200 && strpos($forms['body'], 'grid-auto-rows: max-content') !== false, 'Shared modal field sizing CSS is unavailable.');
    $assert($script['status'] === 200 && strpos($script['body'], "modal.addEventListener('proma:modal-opened', update)") !== false, 'Lazy payment preview script is unavailable.');
    $assert(strpos($script['body'], 'fetchJsonCached') !== false, 'GET request deduplication script is unavailable.');
} finally {
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}

echo "HTTP_V143_MODAL_SMOKE_OK\n";
