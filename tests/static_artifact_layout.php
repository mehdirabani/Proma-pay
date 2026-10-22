<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$builder = (string) file_get_contents($root . '/scripts/build_candidate_delivery_bundle.php');
$candidateBuilder = (string) file_get_contents($root . '/scripts/build_candidate_artifacts.php');
$candidateVerifier = (string) file_get_contents($root . '/tools/verify-candidate-artifacts.php');
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
$assert(
    strpos($candidateBuilder, '/dist/candidates/archive/v*/v*/core/PromaPay-v*.zip') !== false,
    'Candidate generation must continue finding historical baselines in the versioned archive.'
);
$assert(
    strpos($candidateVerifier, "'/dist/candidates/archive/v'") !== false,
    'Candidate verification must resolve versioned archived candidate directories.'
);
$assert(
    strpos($builder, '/dist/candidates/v') !== false && strpos($builder, 'archivedCandidateRoot') === false,
    'Candidate delivery must use a freshly built active candidate, not a historical archive.'
);

echo "STATIC_ARTIFACT_LAYOUT_OK\n";
