<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/RetiredCoreFiles.php';
$fail = static function (string $message): void {
    throw new RuntimeException($message);
};
$expect = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};
$read = static function (string $relative) use ($root, $fail): string {
    $content = file_get_contents($root . '/' . $relative);
    if (!is_string($content)) {
        $fail('Required file is missing or unreadable: ' . $relative);
    }
    return $content;
};

$expect(!is_dir($root . '/laravel'), 'The retired Laravel application directory exists.');
$process = proc_open(
    ['git', '-C', $root, 'ls-files', '-z', '--', 'laravel/'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
$expect(is_resource($process), 'Could not inspect the tracked Laravel path inventory.');
$trackedLaravel = stream_get_contents($pipes[1]);
$gitError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$gitExit = proc_close($process);
$expect($gitExit === 0, 'git ls-files failed: ' . trim((string) $gitError));
$trackedLaravelPresent = [];
foreach (explode("\0", (string) $trackedLaravel) as $trackedPath) {
    $trackedPath = trim($trackedPath);
    if ($trackedPath !== '' && file_exists($root . '/' . $trackedPath)) {
        $trackedLaravelPresent[] = $trackedPath;
    }
}
$expect($trackedLaravelPresent === [], 'Tracked Laravel paths remain in the repository: ' . implode(', ', $trackedLaravelPresent));
$expect(RetiredCoreFiles::allows('wordpress-plugins/proma-pay-woocommerce/proma-pay-woocommerce.php'), 'The old root-level WooCommerce source must be eligible for safe Core-update cleanup.');
$expect(RetiredCoreFiles::pruneBoundary('wordpress-plugins/proma-pay-woocommerce/proma-pay-woocommerce.php') === 'wordpress-plugins', 'The old root-level WooCommerce directory cleanup boundary is missing.');
$expect(!RetiredCoreFiles::allows('plugins/wordpress-plugins/proma-pay-woocommerce/proma-pay-woocommerce.php'), 'The plugin-managed WooCommerce source under plugins/ must be preserved.');

$retiredPaths = [
    'controllers/EcommerceController.php',
    'models/Ecommerce.php',
    'views/ecommerce',
    'database/migrations/2026_07_10_ecommerce_module.sql',
    'database/migrations/2026_07_11_ecommerce_feature_flags.sql',
    'database/migrations/2026_07_11_ecommerce_order_review_fields.sql',
];
foreach ($retiredPaths as $relative) {
    $expect(!file_exists($root . '/' . $relative), 'Retired internal Ecommerce path exists: ' . $relative);
}

$runtimeFiles = [];
foreach (['core', 'controllers', 'models', 'helpers', 'views'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $runtimeFiles[] = $file->getPathname();
        }
    }
}
$runtimeFiles[] = $root . '/install.php';
$runtimeFiles[] = $root . '/database/proma-pay-install.sql';
$forbiddenRuntime = '/(?:ecommerce_(?:products|orders|order_items|installment_requests)|ecommerce_enabled|landing_enabled|email_order_subject|ecommerce_is_enabled|landing_is_enabled|Ecommerce::|EcommerceController)/i';
foreach ($runtimeFiles as $path) {
    if (realpath($path) === realpath($root . '/helpers/RetiredCoreFiles.php')) {
        continue;
    }
    $source = file_get_contents($path);
    $expect(is_string($source), 'Could not read runtime source: ' . $path);
    $expect(!preg_match($forbiddenRuntime, $source), 'Retired Ecommerce runtime dependency remains in ' . substr($path, strlen($root) + 1));
    $expect(!preg_match('/(?:url|redirect|route)\s*\(\s*[\'\"]ecommerce(?:\/|[\'\"])/i', $source), 'A runtime route still links to the retired store: ' . substr($path, strlen($root) + 1));
    $expect(!preg_match('/href\s*=\s*[\'\"][^\'\"]*ecommerce\//i', $source), 'A runtime view still links to the retired store: ' . substr($path, strlen($root) + 1));
}

$router = $read('core/Router.php');
$expectedRetiredRouteGuard = <<<'ROUTEGUARD'
preg_match('/\\Aecommerce(?:\\/|\\z)/i', $route)
ROUTEGUARD;
$expect(str_contains($router, $expectedRetiredRouteGuard), 'Legacy Ecommerce routes are not guarded at the router boundary.');
$expect(str_contains($router, "ErrorHandler::respond(410"), 'Legacy Ecommerce routes do not return HTTP 410.');
$layout = $read('views/layouts/app.php');
$expect(!str_contains($layout, 'تجارت الکترونیک') && !str_contains($layout, 'ecommerce/cart'), 'The internal store menu or cart link remains in the shared layout.');
$installSql = $read('database/proma-pay-install.sql');
$installPhp = $read('install.php');
$expect(!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?ecommerce_/i', $installSql . $installPhp), 'Fresh install SQL creates an Ecommerce table.');
$expect(!preg_match('/(?:ecommerce_enabled|landing_enabled)/i', $installSql . $installPhp), 'Fresh install seeds a retired store setting.');

foreach (['scripts/build_release.php', 'scripts/build_candidate_artifacts.php'] as $builder) {
    $source = $read($builder);
    $expect(str_contains($source, "'laravel/'"), 'Release builder does not explicitly exclude Laravel paths: ' . $builder);
    $expect(str_contains($source, "'retired_files'"), 'Release builder does not describe retired files: ' . $builder);
}
$releaseGate = $read('tools/release-gate.php');
$retirementCli = $read('retire-core-files.php');
$expect(str_contains($retirementCli, "PHP_SAPI !== 'cli'") && str_contains($retirementCli, '--confirm=RETIRE-CORE-FILES'), 'The legacy-updater finalization command is not CLI-only and explicitly confirmed.');
$expect(str_contains($releaseGate, "'tests/retired_modules_invariants.php'"), 'Retired-module invariants are absent from the release gate.');
$expect(str_contains($releaseGate, "'tests/update_retired_file_rollback.php'"), 'Retired-file rollback coverage is absent from the release gate.');
$expect(str_contains($releaseGate, "'tests/update_retired_file_finalize.php'"), 'Legacy-updater finalization coverage is absent from the release gate.');

$history = $read('views/portal/history.php');
$expect(str_contains($history, 'contract') && str_contains($history, 'installment'), 'Contract and installment history was removed with the store.');
$email = $read('helpers/EmailService.php');
$expect(str_contains($email, 'function send') && !str_contains($email, 'orderSuccessTemplate'), 'Generic email service was removed or order email remains.');
foreach (['PromaInventoryPartners', 'PromaAffiliate', 'PromaAccounting', 'PromaRepair', 'PromaSignConnect', 'PromaZarinpal'] as $plugin) {
    $expect(is_file($root . '/plugins/' . $plugin . '/plugin.json'), 'Official plugin manifest is missing: ' . $plugin);
}

echo "RETIRED_MODULE_INVARIANTS_OK\n";
