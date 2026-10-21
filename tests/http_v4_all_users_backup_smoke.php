<?php

declare(strict_types=1);

if (getenv('PROMA_TEST_ALLOW_ALL_USER_LOGIN') !== '1') {
    fwrite(STDERR, "Set PROMA_TEST_ALLOW_ALL_USER_LOGIN=1 for the isolated all-user login sweep.\n");
    exit(2);
}

$dsn = trim((string) getenv('PROMA_TEST_DB_DSN'));
if (!preg_match('/(?:^|;)dbname=(?:proma_qa_login_sweep_v4|proma_qa_v404_20261008)(?:;|$)/i', $dsn)) {
    fwrite(STDERR, "The all-user login sweep is restricted to its dedicated clone database.\n");
    exit(2);
}
$baseUrl = rtrim((string) getenv('PROMA_QA_BASE_URL'), '/');
$qaHost = strtolower(trim((string) parse_url($baseUrl, PHP_URL_HOST), '[]'));
if ($baseUrl === '' || !in_array($qaHost, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "The all-user login sweep requires a loopback QA server.\n");
    exit(2);
}
if (!function_exists('curl_init')) {
    fwrite(STDERR, "PHP cURL extension is required for the HTTP login sweep.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] = 'qa.local';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname(__DIR__) . '/bootstrap.php';

$pdo = new PDO(
    $dsn,
    (string) (getenv('PROMA_TEST_DB_USER') ?: 'root'),
    (string) (getenv('PROMA_TEST_DB_PASSWORD') ?: ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdoProperty = new ReflectionProperty(Model::class, 'pdo');

$pdoProperty->setValue(null, $pdo);

$allUsers = $pdo->query('SELECT id, role, status, username, password_hash, last_login_at, mobile FROM users ORDER BY id')->fetchAll();
if (count($allUsers) !== 271) {
    fwrite(STDERR, "The backup-derived login clone no longer has the expected account count.\n");
    exit(2);
}
$onlyRole = trim((string) getenv('PROMA_QA_ONLY_ROLE'));
$users = $onlyRole === ''
    ? $allUsers
    : array_values(array_filter($allUsers, static fn(array $user): bool => (string) $user['role'] === $onlyRole));
if (!$users) {
    fwrite(STDERR, "The requested role does not exist in the backup-derived login clone.\n");
    exit(2);
}

$request = static function (string $url, string $cookieFile, ?array $post = null): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'PromaPay-V4-Backup-Account-QA',
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    $ok = $body !== false;
    curl_close($curl);
    return ['ok' => $ok, 'status' => $status, 'body' => $ok ? (string) $body : '', 'url' => $effectiveUrl];
};
$routeUrl = static fn(string $route): string => $baseUrl . '/index.php?route=' . rawurlencode($route);
$failureCounts = [];
$roleCounts = [];
$rowStateRestored = false;
$temporaryPassword = 'Proma-QA-V4-' . bin2hex(random_bytes(16)) . '!';
$temporaryHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
$setTemporaryIdentity = $pdo->prepare('UPDATE users SET username = ?, password_hash = ? WHERE id = ?');
$setTemporaryCustomerIdentity = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
$restoreIdentity = $pdo->prepare('UPDATE users SET username = ?, password_hash = ?, last_login_at = ? WHERE id = ?');
$findIdentity = $pdo->prepare('SELECT username, password_hash, last_login_at FROM users WHERE id = ? LIMIT 1');
$fail = static function (string $role, string $reason) use (&$failureCounts): void {
    $failureCounts[$role][$reason] = ($failureCounts[$role][$reason] ?? 0) + 1;
};

try {
    foreach ($users as $user) {
        $id = (int) $user['id'];
        $role = (string) $user['role'];
        $status = (string) $user['status'];
        $roleCounts[$role] ??= ['allowed' => 0, 'denied' => 0, 'failed' => 0];
        $roleCanLogin = $status === 'active' && PluginManager::roleCanLogin($role, $user);
        $customerPin = $role === 'customer' ? User::customerPasswordFromMobile((string) ($user['mobile'] ?? '')) : '';
        if ($roleCanLogin && $role === 'customer' && $customerPin === '') {
            $fail($role, 'active_customer_missing_mobile_pin');
            $roleCounts[$role]['failed']++;
            continue;
        }
        $loginPassword = $role === 'customer' ? ($customerPin !== '' ? $customerPin : '0000') : $temporaryPassword;
        $temporaryUsername = 'qa_v4_' . $id . '_' . bin2hex(random_bytes(8));
        $cookieFile = tempnam(sys_get_temp_dir(), 'proma-v4-user-');
        try {
            if ($role === 'customer') {
                // Exercise the real four-digit mobile PIN without changing the customer's stored hash.
                $setTemporaryCustomerIdentity->execute([$temporaryUsername, $id]);
            } else {
                $setTemporaryIdentity->execute([$temporaryUsername, $temporaryHash, $id]);
            }
            $loginPage = $request($routeUrl('auth/login'), $cookieFile);
            if (!$loginPage['ok'] || $loginPage['status'] !== 200
                || preg_match('/Fatal error|Uncaught|Parse error|SQLSTATE\[|Warning:\s/i', $loginPage['body'])) {
                $fail($role, 'login_page');
                $roleCounts[$role]['failed']++;
                continue;
            }
            $matched = preg_match('/name=["\']_csrf["\'][^>]*value=["\']([^"\']+)["\']/i', $loginPage['body'], $csrfMatch);
            if ($matched !== 1) {
                $matched = preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']_csrf["\']/i', $loginPage['body'], $csrfMatch);
            }
            if ($matched !== 1) {
                $fail($role, 'csrf');
                $roleCounts[$role]['failed']++;
                continue;
            }

            $login = $request($routeUrl('auth/login'), $cookieFile, [
                '_csrf' => html_entity_decode((string) $csrfMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'identifier' => $temporaryUsername,
                'password' => $loginPassword,
            ]);
            $loginHasRuntimeError = preg_match('/Fatal error|Uncaught|Parse error|SQLSTATE\[|Warning:\s/i', $login['body']) === 1;
            if (!$login['ok'] || $login['status'] !== 200 || $loginHasRuntimeError) {
                $loginReason = !$login['ok'] ? 'login_transport' : ($login['status'] !== 200 ? 'login_http_' . $login['status'] : 'login_runtime_error');
                $fail($role, $loginReason);
                $roleCounts[$role]['failed']++;
                continue;
            }
            $loginQuery = [];
            parse_str((string) parse_url($login['url'], PHP_URL_QUERY), $loginQuery);
            $authenticated = ($loginQuery['route'] ?? '') !== 'auth/login';
            if ($roleCanLogin) {
                $pluginLanding = PluginManager::roleLandingRoute($role);
                $expectedRoute = $role === 'operator' ? 'overdue' : ($pluginLanding ?: 'dashboard');
                if (!$authenticated || ($loginQuery['route'] ?? '') !== $expectedRoute || strlen(trim(strip_tags($login['body']))) < 80) {
                    $fail($role, 'active_login_or_landing');
                    $roleCounts[$role]['failed']++;
                    continue;
                }
                $roleCounts[$role]['allowed']++;
            } else {
                if ($authenticated) {
                    $fail($role, 'unexpected_login_allowed');
                    $roleCounts[$role]['failed']++;
                    continue;
                }
                $roleCounts[$role]['denied']++;
            }
        } catch (Throwable $error) {
            $fail($role, 'request_or_database');
            $roleCounts[$role]['failed']++;
        } finally {
            if (is_file($cookieFile)) {
                unlink($cookieFile);
            }
        }
    }
} finally {
    foreach ($allUsers as $user) {
        $restoreIdentity->execute([
            $user['username'],
            $user['password_hash'],
            $user['last_login_at'],
            (int) $user['id'],
        ]);
    }
    $rowStateRestored = true;
    foreach ($allUsers as $user) {
        $findIdentity->execute([(int) $user['id']]);
        $current = $findIdentity->fetch();
        if (!$current || $current['username'] !== $user['username']
            || $current['password_hash'] !== $user['password_hash']
            || $current['last_login_at'] !== $user['last_login_at']) {
            $rowStateRestored = false;
            break;
        }
    }
}

$activeAllowed = 0;
$denied = 0;
$failures = array_sum(array_map('array_sum', $failureCounts));
foreach ($roleCounts as $counts) {
    $activeAllowed += $counts['allowed'];
    $denied += $counts['denied'];
}
$roleSummary = [];
ksort($roleCounts);
foreach ($roleCounts as $role => $counts) {
    $roleSummary[] = $role . ':' . $counts['allowed'] . '/' . $counts['denied'] . '/' . $counts['failed'];
}
if (!$rowStateRestored) {
    fwrite(STDERR, "ALL_BACKUP_USERS_HTTP_FAILED user state restoration could not be verified.\n");
    exit(1);
}
if ($failures > 0) {
    $reasonSummary = [];
    ksort($failureCounts);
    foreach ($failureCounts as $role => $reasons) {
        ksort($reasons);
        foreach ($reasons as $reason => $count) {
            $reasonSummary[] = $role . ':' . $reason . ':' . $count;
        }
    }
    fwrite(STDERR, 'ALL_BACKUP_USERS_HTTP_FAILED total=' . count($allUsers) . ' attempted=' . count($users) . ' failures=' . $failures . ' denied=' . $denied . ' roles=' . implode(',', $roleSummary) . ' reasons=' . implode(',', $reasonSummary) . "\n");
    exit(1);
}
echo 'ALL_BACKUP_USERS_HTTP_OK total=' . count($allUsers) . ' attempted=' . count($users) . ' authenticated=' . $activeAllowed . ' denied=' . $denied . ' user_rows_restored=' . ($rowStateRestored ? count($allUsers) : 0) . ' roles=' . implode(',', $roleSummary) . "\n";
