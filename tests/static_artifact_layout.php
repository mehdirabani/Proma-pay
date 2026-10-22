<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$builder = (string) file_get_contents($root . '/scripts/build_candidate_delivery_bundle.php');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(
    strpos($builder, '$deliveryRoot = $root . \'/dist/releases/v\' . $version;') !== false,
    'Candidate delivery bundles must be grouped under a version-specific releases directory.'
);
$assert(
    strpos($builder, '$bundlePath = $deliveryRoot . \'/PromaPay-v\' . $version . \'-Candidate-Delivery\'') !== false,
    'Candidate delivery bundle names must retain the version and candidate build identifier.'
);
$assert(
    strpos($builder, '$checksumPath = $bundlePath . \'.sha256\';') !== false,
    'Candidate delivery checksums must remain beside their matching bundle.'
);

echo "STATIC_ARTIFACT_LAYOUT_OK\n";
