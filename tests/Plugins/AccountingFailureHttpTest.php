<?php

declare(strict_types=1);

$baseUrl = rtrim((string) getenv('PROMA_QA_BASE_URL'), '/');
$dsn = (string) getenv('PROMA_TEST_DB_DSN');
$username = (string) getenv('PROMA_QA_ADMIN_USER');
$password = (string) getenv('PROMA_QA_ADMIN_PASSWORD');
if ($baseUrl === '' || $dsn === '' || $username === '' || $password === '') {
    fwrite(STDERR, "QA URL, database and admin credentials are required.\n");
    exit(2);
}
$pdo = new PDO($dsn, getenv('PROMA_TEST_DB_USER') ?: 'root', getenv('PROMA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$plugin = $pdo->query("SELECT status, manifest_json FROM system_plugins WHERE plugin_id='proma-accounting' LIMIT 1")->fetch();
if (!$plugin) throw new RuntimeException('Accounting plugin registry row is missing.');

$cookie = tempnam(sys_get_temp_dir(), 'proma-plugin-failure-');
$request = static function ($route, $cookie, array $post = null, $follow = true) use ($baseUrl) {
    $ch = curl_init($baseUrl . '/index.php?route=' . rawurlencode($route));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_MAXREDIRS => 5, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException($error);
    return ['status' => $status, 'body' => $body];
};

try {
    $loginPage = $request('auth/login', $cookie);
    if (!preg_match('/name=["\']_csrf["\'][^>]*value=["\']([^"\']+)/i', $loginPage['body'], $match)) throw new RuntimeException('Login CSRF token missing.');
    $login = $request('auth/login', $cookie, ['_csrf' => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'identifier' => $username, 'password' => $password]);
    if ($login['status'] !== 200) throw new RuntimeException('Admin login failed.');

    $pdo->exec('RENAME TABLE plugin_accounting_commissions TO plugin_accounting_commissions_qa_hold');
    try {
        $degraded = $request('plugin/accounting/dashboard', $cookie);
        if ($degraded['status'] !== 200 || strpos($degraded['body'], 'شناسه پیگیری') === false || strpos($degraded['body'], 'موقتاً در دسترس نیست') === false) {
            throw new RuntimeException('Accounting query failure did not produce the degraded dashboard.');
        }
    } finally {
        $pdo->exec('RENAME TABLE plugin_accounting_commissions_qa_hold TO plugin_accounting_commissions');
    }

    $invalidManifest = json_encode(['id' => 'proma-accounting'], JSON_UNESCAPED_SLASHES);
    $statement = $pdo->prepare("UPDATE system_plugins SET status='active', manifest_json=? WHERE plugin_id='proma-accounting'");
    $statement->execute([$invalidManifest]);
    $core = $request('dashboard', $cookie);
    $live = $request('health/live', $cookie, null, false);
    if ($core['status'] !== 200 || $live['status'] !== 200 || strpos($live['body'], '"ok":true') === false) {
        throw new RuntimeException('Core did not remain responsive after plugin bootstrap failure.');
    }
    echo "PASSED AccountingFailureHttpTest\n";
} finally {
    $restore = $pdo->prepare("UPDATE system_plugins SET status=?, manifest_json=?, last_error=NULL WHERE plugin_id='proma-accounting'");
    $restore->execute([$plugin['status'], $plugin['manifest_json']]);
    if (is_file($cookie)) unlink($cookie);
}
