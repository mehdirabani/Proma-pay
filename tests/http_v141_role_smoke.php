<?php

declare(strict_types=1);

if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP cURL extension is required.\n");
    exit(2);
}

$baseUrl = rtrim((string) getenv('PROMA_QA_BASE_URL'), '/');
if ($baseUrl === '') {
    fwrite(STDERR, "PROMA_QA_BASE_URL is required.\n");
    exit(2);
}

$roles = [
    'admin' => [
        'identifier' => getenv('PROMA_QA_ADMIN_USER') ?: '',
        'password' => getenv('PROMA_QA_ADMIN_PASSWORD') ?: '',
        'routes' => ['dashboard', 'notifications', 'users', 'profile-reviews', 'customers', 'contracts', 'installments', 'overdue', 'payments', 'review', 'legal', 'chat', 'calendar', 'file-manager', 'medals', 'plugins', 'settings', 'system-health'],
        'forbidden' => null,
    ],
    'operator' => [
        'identifier' => getenv('PROMA_QA_OPERATOR_USER') ?: '',
        'password' => getenv('PROMA_QA_OPERATOR_PASSWORD') ?: '',
        'routes' => ['dashboard', 'calendar', 'notifications', 'overdue', 'contracts', 'chat'],
        'forbidden' => 'settings',
    ],
    'lawyer' => [
        'identifier' => getenv('PROMA_QA_LAWYER_USER') ?: '',
        'password' => getenv('PROMA_QA_LAWYER_PASSWORD') ?: '',
        'routes' => ['dashboard', 'calendar', 'notifications', 'lawyer', 'chat'],
        'forbidden' => 'settings',
    ],
    'customer' => [
        'identifier' => getenv('PROMA_QA_CUSTOMER_USER') ?: '',
        'password' => getenv('PROMA_QA_CUSTOMER_PASSWORD') ?: '',
        'routes' => ['dashboard', 'notifications', 'portal/contracts', 'installments/panel', 'ecommerce/landing', 'ecommerce/cart', 'ecommerce/myOrders', 'portal/guaranteed', 'portal/history', 'calendar', 'chat', 'profile'],
        'forbidden' => 'users',
    ],
];

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$routeUrl = static function (string $route) use ($baseUrl): string {
    return $baseUrl . '/index.php?route=' . rawurlencode($route);
};

$request = static function (string $url, string $cookieFile, ?array $post = null, bool $follow = true): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'PromaPay-V1.4.1-QA',
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

$assertHealthy = static function (array $response, string $label) use ($assert): void {
    $assert($response['status'] === 200, $label . ' returned HTTP ' . $response['status'] . '.');
    $body = trim(strip_tags((string) $response['body']));
    $assert(strlen($body) >= 80, $label . ' rendered an empty or incomplete page.');
    $assert(!preg_match('/Fatal error|Uncaught|Parse error|SQLSTATE\[|Warning:\s/i', (string) $response['body']), $label . ' exposed a PHP or SQL failure.');
};

foreach ($roles as $role => $spec) {
    $assert($spec['identifier'] !== '' && $spec['password'] !== '', 'Missing QA credentials for role: ' . $role);
    $cookieFile = tempnam(sys_get_temp_dir(), 'proma-http-' . $role . '-');
    try {
        $loginPage = $request($routeUrl('auth/login'), $cookieFile);
        $assertHealthy($loginPage, $role . ' login page');
        $matched = preg_match('/name=["\']_csrf["\'][^>]*value=["\']([^"\']+)["\']/i', $loginPage['body'], $csrfMatch);
        if ($matched !== 1) {
            $matched = preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']_csrf["\']/i', $loginPage['body'], $csrfMatch);
        }
        $assert($matched === 1, 'CSRF token missing on ' . $role . ' login page.');

        $login = $request($routeUrl('auth/login'), $cookieFile, [
            '_csrf' => html_entity_decode((string) $csrfMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'identifier' => $spec['identifier'],
            'password' => $spec['password'],
        ]);
        $assertHealthy($login, $role . ' login result');
        $assert(strpos($login['url'], 'route=auth') === false, $role . ' authentication did not leave the login route.');

        foreach ($spec['routes'] as $route) {
            $response = $request($routeUrl($route), $cookieFile);
            $assertHealthy($response, $role . ' route ' . $route);
            $assert(strpos($response['url'], 'route=auth') === false, $role . ' lost its session on ' . $route . '.');
        }

        if (is_string($spec['forbidden'])) {
            $forbidden = $request($routeUrl($spec['forbidden']), $cookieFile, null, false);
            $assert($forbidden['status'] === 403, $role . ' forbidden route did not return HTTP 403.');
        }
    } finally {
        if (is_file($cookieFile)) {
            unlink($cookieFile);
        }
    }
}

echo "HTTP_V141_ROLE_SMOKE_OK\n";
