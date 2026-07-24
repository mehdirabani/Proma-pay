<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(($version['application'] ?? '') === '1.4.5', 'Core version must be V1.4.5.');
$assert(($version['release_channel'] ?? '') === 'stable', 'V1.4.5 release channel must be stable.');
$assert(trim((string) file_get_contents($root . '/health-static.txt')) === 'proma-static-ok', 'Static health probe is missing.');

$router = (string) file_get_contents($root . '/core/Router.php');
foreach ([
    "'users/retire' => 'users/delete'",
    "'contracts/retireLegalLog' => 'contracts/deleteLegalLog'",
    "'plugins/retireFromHost' => 'plugins/deleteFromHost'",
    "'backup/clearLogs' => 'backup/deleteLogs'",
] as $alias) {
    $assert(strpos($router, $alias) !== false, 'WAF-neutral route alias is missing: ' . $alias);
}

$appJs = (string) file_get_contents($root . '/assets/js/app.js');
foreach ([
    'interval: 15000',
    'hiddenInterval: 90000',
    "leaseKey: 'chat:'",
    'interval: 45000',
    "leaseKey: 'notifications:'",
    'document.body.appendChild(modal)',
    '--proma-visual-offset-top',
] as $token) {
    $assert(strpos($appJs, $token) !== false, 'Adaptive request/modal token is missing: ' . $token);
}

$chatController = (string) file_get_contents($root . '/controllers/ChatController.php');
$fetchStart = strpos($chatController, 'public function fetch');
$fetchEnd = strpos($chatController, 'public function attachment', $fetchStart);
$fetchSource = substr($chatController, $fetchStart, $fetchEnd - $fetchStart);
$assert(strpos($fetchSource, 'Chat::unreadCount') === false, 'Chat polling still performs global unread counts.');
$assert(strpos($fetchSource, 'if ($messages)') !== false, 'Chat polling writes read state without a new-message guard.');

$chatModel = (string) file_get_contents($root . '/models/Chat.php');
$assert(substr_count($chatModel, 'LIMIT 100') >= 2, 'Chat history and polling batches are not bounded.');
$assert(strpos($chatModel, 'markChannelRead($userId, $channelId, $messageId = null)') !== false, 'Channel read state cannot reuse the fetched message id.');

$healthController = (string) file_get_contents($root . '/controllers/SystemHealthController.php');
$healthView = (string) file_get_contents($root . '/views/system-health/index.php');
$assert(strpos($healthController, '/health-static.txt') !== false, 'Static network probe is absent from system health.');
$assert(strpos($healthView, 'آزمون استاتیک') !== false && strpos($healthView, 'proma-health-diagnostic-grid') !== false, 'Layered timeout diagnosis UI is incomplete.');

$responsiveCss = (string) file_get_contents($root . '/assets/css/components/responsive.css');
$assert(strpos($responsiveCss, 'inset: var(--proma-visual-offset-top, 0) 0 auto !important') !== false, 'Mobile modal visual viewport offset is missing.');

$generatedActionPattern = '/action\s*=\s*["\'][^"\']*(?:delete|purge|destroy|truncate)/i';
foreach (['views', 'plugins'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        $assert(!preg_match($generatedActionPattern, $source), 'Generated destructive action remains in ' . $file->getPathname());
    }
}

$accounting = json_decode((string) file_get_contents($root . '/plugins/PromaAccounting/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($accounting['version'] ?? '') === '1.2.6', 'Proma Accounting version must be V1.2.6.');

echo "STATIC_V145_OK\n";
