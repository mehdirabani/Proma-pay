<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$version = require $root . '/config/version.php';
$assert(version_compare((string) ($version['application'] ?? '0.0.0'), '1.4.9', '>='), 'Core version must not regress below V1.4.9.');
$assert(($version['release_channel'] ?? '') === 'stable', 'Current core release channel must be stable.');
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
    'minimumGapMs: 750',
    'interval: 60000',
    'hiddenInterval: 600000',
    "leaseKey: 'chat:'",
    'interval: 180000',
    'hiddenInterval: 900000',
    "leaseKey: 'notifications:'",
    'document.body.appendChild(modal)',
    '--proma-visual-offset-top',
] as $token) {
    $assert(strpos($appJs, $token) !== false, 'Adaptive request/modal token is missing: ' . $token);
}
$floodGuard = (string) file_get_contents($root . '/core/RequestFloodGuard.php');
$bootstrap = (string) file_get_contents($root . '/bootstrap.php');
$htaccess = (string) file_get_contents($root . '/.htaccess');
$assert(strpos($floodGuard, 'class RequestFloodGuard') !== false && strpos($floodGuard, "'chat-poll'") !== false, 'Shared-host request flood guard is missing.');
$assert(strpos($bootstrap, 'RequestFloodGuard::enforce();') !== false, 'Request flood guard does not run before session/database work.');
$assert(strpos($htaccess, 'max-age=31536000, immutable') !== false, 'Runtime asset caching for shared hosting is missing.');

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

$contractsController = (string) file_get_contents($root . '/controllers/ContractsController.php');
$contractModel = (string) file_get_contents($root . '/models/Contract.php');
$paymentModel = (string) file_get_contents($root . '/models/Payment.php');
$contractDocument = (string) file_get_contents($root . '/models/ContractDocument.php');
$contractsView = (string) file_get_contents($root . '/views/contracts/index.php');
$settingsModel = (string) file_get_contents($root . '/models/Settings.php');
$assert(strpos($contractsController, 'Contract::installmentStatsForContracts') !== false, 'Contract index does not preload installment stats in one batch.');
$assert(strpos($contractsController, 'Payment::monthlyTrendsForContracts') !== false && strpos($contractsController, 'Payment::recentForContracts') !== false, 'Contract index still lacks batched payment data.');
$assert(strpos($contractModel, 'public static function installmentStatsForContracts') !== false && strpos($contractModel, 'public static function guarantorsForContracts') !== false, 'Contract page batch helpers are missing.');
$assert(strpos($contractModel, 'public static function syncCompletionStatusesForContracts') !== false, 'Maintenance completion sync is not bounded to changed contracts.');
$assert(strpos($paymentModel, 'public static function monthlyTrendsForContracts') !== false && strpos($paymentModel, 'public static function recentForContracts') !== false, 'Payment page batch helpers are missing.');
$assert(strpos($paymentModel, "Contract::syncCompletionStatuses((int) (\$row['contract_id'] ?? 0))") !== false, 'Payment completion sync is not scoped to its contract.');
$assert(strpos($contractDocument, 'itemsForContracts') !== false && strpos($contractDocument, 'guaranteesForContracts') !== false, 'Edit-dialog document data is not batched.');
$assert(strpos($contractsView, 'Contract::installmentStats(') === false && strpos($contractsView, 'Payment::monthlyTrendForContract(') === false, 'Contract view still has per-card payment/stat queries.');
$assert(strpos($contractsView, 'Contract::deletionPreview(') === false && strpos($contractsView, 'Contract::cancellationSummary(') === false, 'Destructive-dialog previews still run for every card.');
$settingsEnsureStart = strpos($settingsModel, 'public static function ensureSchema()');
$settingsEnsureEnd = strpos($settingsModel, 'public static function defaults()', $settingsEnsureStart);
$settingsEnsure = substr($settingsModel, $settingsEnsureStart, $settingsEnsureEnd - $settingsEnsureStart);
$assert(strpos($settingsEnsure, 'self::execute(') === false && strpos($settingsEnsure, 'seedCalendarDefaults') === false, 'Settings initialization still writes during ordinary requests.');
$sharedHostMigration = (string) file_get_contents($root . '/database/migrations/2026_07_26_shared_host_request_path.sql');
$assert(strpos($sharedHostMigration, 'idx_legal_contract_status') !== false && strpos($sharedHostMigration, 'idx_payment_contract_status') !== false, 'Shared-host request indexes are missing.');

$installer = (string) file_get_contents($root . '/install.php');
$canonicalSchema = (string) file_get_contents($root . '/database/proma-pay-install.sql');
$assert(strpos($installer, "database/proma-pay-install.sql") !== false, 'Fresh installer does not use the canonical release schema.');
foreach (['`delivered_at`', '`seen_at`', '`archived_at`', '`deleted_at`', '`actioned_at`', '`dedupe_key`'] as $column) {
    $assert(strpos($canonicalSchema, $column) !== false, 'Canonical notification schema is missing ' . $column . '.');
}

$releaseBuilder = (string) file_get_contents($root . '/scripts/build_release.php');
$releaseVerifier = (string) file_get_contents($root . '/tools/verify-release.php');
foreach ([$releaseBuilder, $releaseVerifier] as $releaseTool) {
    $assert(strpos($releaseTool, "'/dist/updates") !== false, 'Release update artifacts must use dist/updates.');
}
$assert(strpos($releaseBuilder, "'html/RTL/assets'") !== false, 'Release packaging must include the RTL theme runtime assets.');
$assert(strpos($releaseVerifier, 'required UI runtime asset') !== false, 'Release verification must require the RTL theme runtime assets.');
$assert(strpos($releaseBuilder, 'PROMA_UPDATE_BASELINE_ARCHIVE') !== false && strpos($releaseBuilder, 'release-manifest.json') !== false, 'Release builder does not calculate the update against a verified core baseline.');
$assert(strpos($releaseBuilder, 'legacy duplicate release alias') !== false, 'Release builder still creates duplicate dashed aliases.');
$assert(strpos($releaseVerifier, 'Legacy duplicate release alias remains') !== false, 'Release verification does not reject duplicate dashed aliases.');

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
$assert(version_compare((string) ($accounting['version'] ?? '0.0.0'), '1.2.7', '>='), 'Proma Accounting version must not regress below V1.2.7.');

echo "STATIC_V145_OK\n";
