<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/functions.php';
require_once $root . '/helpers/ScriptUpdateService.php';

$assert = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$method = new ReflectionMethod(ScriptUpdateService::class, 'packageCompatibility');
$method->setAccessible(true);

$versionInfo = require $root . '/config/version.php';
$currentVersion = (string) ($versionInfo['application'] ?? '1.4.4');

$same = $method->invoke(null, ['version' => 'V' . $currentVersion, 'minimum_version' => '1.4.2']);
$assert(empty($same['is_installable']) && $same['state'] === 'current', 'Same-version package must be rejected before installation.');

$older = $method->invoke(null, ['version' => '1.4.3', 'minimum_version' => '1.4.2']);
$assert(empty($older['is_installable']) && $older['state'] === 'older', 'Older package must be rejected.');

$segments = array_map('intval', explode('.', $currentVersion));
$nextVersion = ($segments[0] ?? 1) . '.' . ($segments[1] ?? 0) . '.' . (($segments[2] ?? 0) + 1);
$minimumAfterNext = ($segments[0] ?? 1) . '.' . ($segments[1] ?? 0) . '.' . (($segments[2] ?? 0) + 2);

$upgrade = $method->invoke(null, ['version' => $nextVersion, 'minimum_version' => '1.4.2']);
$assert(!empty($upgrade['is_installable']) && $upgrade['state'] === 'upgrade', 'Newer compatible package must remain installable.');

$incompatible = $method->invoke(null, ['version' => $minimumAfterNext, 'minimum_version' => $minimumAfterNext]);
$assert(empty($incompatible['is_installable']) && $incompatible['state'] === 'incompatible', 'Minimum-version guard is not enforced.');

echo "UPDATE_V144_OK\n";
