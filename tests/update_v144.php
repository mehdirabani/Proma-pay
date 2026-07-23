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

$same = $method->invoke(null, ['version' => 'V1.4.4', 'minimum_version' => '1.4.2']);
$assert(empty($same['is_installable']) && $same['state'] === 'current', 'Same-version package must be rejected before installation.');

$older = $method->invoke(null, ['version' => '1.4.3', 'minimum_version' => '1.4.2']);
$assert(empty($older['is_installable']) && $older['state'] === 'older', 'Older package must be rejected.');

$upgrade = $method->invoke(null, ['version' => '1.4.5', 'minimum_version' => '1.4.2']);
$assert(!empty($upgrade['is_installable']) && $upgrade['state'] === 'upgrade', 'Newer compatible package must remain installable.');

$incompatible = $method->invoke(null, ['version' => '1.5.0', 'minimum_version' => '1.4.5']);
$assert(empty($incompatible['is_installable']) && $incompatible['state'] === 'incompatible', 'Minimum-version guard is not enforced.');

echo "UPDATE_V144_OK\n";
