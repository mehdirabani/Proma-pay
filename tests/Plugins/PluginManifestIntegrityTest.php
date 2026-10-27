<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/core/PluginManager.php';
$core = require $root . '/config/version.php';
$pluginApi = (string) ($core['plugin_api'] ?? '0.0');
$pluginsRoot = $root . '/plugins';
$failures = [];
$manifestCount = 0;
$routeCount = 0;
$allManifests = [];
$manifestPaths = glob($pluginsRoot . '/*/plugin.json') ?: [];
$knownPluginIds = [];
foreach ($manifestPaths as $knownManifestPath) {
    try {
        $knownManifest = json_decode((string) file_get_contents($knownManifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (is_array($knownManifest) && !empty($knownManifest['id'])) $knownPluginIds[(string) $knownManifest['id']] = true;
    } catch (Throwable $ignored) {
    }
}

$isTrivia = static function ($token): bool {
    return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
};
$nextSignificant = static function (array $tokens, int $index) use ($isTrivia): int {
    while (isset($tokens[$index]) && $isTrivia($tokens[$index])) $index++;
    return $index;
};
$declaredSymbols = static function (string $file) use ($isTrivia, $nextSignificant): array {
    $tokens = token_get_all((string) file_get_contents($file), TOKEN_PARSE);
    $namespace = '';
    $symbols = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i])) continue;
        if ($tokens[$i][0] === T_NAMESPACE) {
            $name = '';
            for ($j = $i + 1; $j < $count; $j++) {
                $token = $tokens[$j];
                if ($token === ';' || $token === '{') break;
                if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                    $name .= $token[1];
                }
            }
            $namespace = trim($name, '\\');
            continue;
        }
        if (!in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) continue;
        $previous = $i - 1;
        while ($previous >= 0 && $isTrivia($tokens[$previous])) $previous--;
        if ($previous >= 0 && is_array($tokens[$previous]) && in_array($tokens[$previous][0], [T_NEW, T_DOUBLE_COLON], true)) continue;
        $nameIndex = $nextSignificant($tokens, $i + 1);
        if (!isset($tokens[$nameIndex]) || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) continue;
        $className = ($namespace !== '' ? $namespace . '\\' : '') . $tokens[$nameIndex][1];
        $bodyOpen = $nameIndex + 1;
        while ($bodyOpen < $count && $tokens[$bodyOpen] !== '{') $bodyOpen++;
        if ($bodyOpen >= $count) continue;
        $depth = 1;
        $methods = [];
        for ($j = $bodyOpen + 1; $j < $count && $depth > 0; $j++) {
            if ($tokens[$j] === '{') { $depth++; continue; }
            if ($tokens[$j] === '}') { $depth--; continue; }
            if ($depth !== 1 || !is_array($tokens[$j]) || $tokens[$j][0] !== T_FUNCTION) continue;
            $methodIndex = $nextSignificant($tokens, $j + 1);
            if (($tokens[$methodIndex] ?? null) === '&') $methodIndex = $nextSignificant($tokens, $methodIndex + 1);
            if (!isset($tokens[$methodIndex]) || !is_array($tokens[$methodIndex]) || $tokens[$methodIndex][0] !== T_STRING) continue;
            $visibility = null;
            for ($k = $j - 1, $steps = 0; $k > $bodyOpen && $steps < 12; $k--, $steps++) {
                $candidate = $tokens[$k];
                if ($candidate === ';' || $candidate === '{' || $candidate === '}') break;
                if (is_array($candidate) && in_array($candidate[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                    $visibility = $candidate[0];
                    break;
                }
            }
            if ($visibility === T_PUBLIC) $methods[$tokens[$methodIndex][1]] = true;
        }
        $symbols[$className] = $methods;
        $i = $bodyOpen;
    }
    return $symbols;
};

foreach ($manifestPaths as $manifestPath) {
    $manifestCount++;
    $pluginRoot = dirname($manifestPath);
    $pluginName = basename($pluginRoot);
    try {
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        $failures[] = $pluginName . ': invalid plugin.json (' . $error->getMessage() . ')';
        continue;
    }
    if (!is_array($manifest)) {
        $failures[] = $pluginName . ': plugin.json root must be an object';
        continue;
    }
    $manifestId = trim((string) ($manifest['id'] ?? ''));
    if ($manifestId === '') {
        $failures[] = $pluginName . ': plugin id is required';
    } elseif (isset($allManifests[$manifestId])) {
        $failures[] = $pluginName . ': duplicate plugin id ' . $manifestId;
    } else {
        $allManifests[$manifestId] = $manifest;
    }

    $symbols = [];
    $sourceRoot = $pluginRoot . '/src';
    if (is_dir($sourceRoot)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
            try {
                $symbols += $declaredSymbols($file->getPathname());
            } catch (Throwable $error) {
                $failures[] = $pluginName . ': PHP source cannot be parsed: ' . str_replace($pluginRoot . '/', '', $file->getPathname()) . ' (' . $error->getMessage() . ')';
            }
        }
    }

    $provider = trim((string) ($manifest['provider'] ?? ''), '\\');
    if ($provider === '' || !array_key_exists($provider, $symbols)) {
        $failures[] = $pluginName . ': provider class missing: ' . ($provider ?: '(empty)');
    }
    $requiresCore = (string) ($manifest['requires_core'] ?? '');
    if (!preg_match('/^>=\s*(\d+\.\d+\.\d+)$/', $requiresCore, $minimum) || version_compare($minimum[1] ?? '0.0.0', (string) ($core['application'] ?? '0.0.0'), '>')) {
        $failures[] = $pluginName . ': invalid or incompatible requires_core value: ' . $requiresCore;
    }
    $requiredApi = (string) ($manifest['plugin_api_version'] ?? '');
    if ($requiredApi === '' || version_compare($requiredApi, $pluginApi, '>')) {
        $failures[] = $pluginName . ': plugin API ' . ($requiredApi ?: '(empty)') . ' exceeds Core API ' . $pluginApi;
    }

    $permissionKeys = [];
    foreach (($manifest['permissions'] ?? []) as $permission) {
        $key = trim((string) ($permission['key'] ?? ''));
        if ($key === '') { $failures[] = $pluginName . ': permission has no key'; continue; }
        if (isset($permissionKeys[$key])) $failures[] = $pluginName . ': duplicate permission ' . $key;
        $permissionKeys[$key] = true;
    }

    $routes = [];
    foreach (($manifest['routes'] ?? []) as $route) {
        $routeCount++;
        if (!is_array($route)) { $failures[] = $pluginName . ': route entry is not an object'; continue; }
        $path = trim((string) ($route['path'] ?? ''));
        $method = strtoupper(trim((string) ($route['method'] ?? 'GET')));
        $pair = $method . ' ' . $path;
        if ($path === '' || !in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)) {
            $failures[] = $pluginName . ': invalid route path or HTTP method: ' . $pair;
        }
        if (isset($routes[$pair])) $failures[] = $pluginName . ': duplicate route ' . $pair;
        $routes[$pair] = true;
        $handler = trim((string) ($route['handler'] ?? ''));
        if (!preg_match('/^(.+)@([A-Za-z_][A-Za-z0-9_]*)$/', $handler, $parts)) {
            $failures[] = $pluginName . ': invalid handler: ' . ($handler ?: '(empty)');
            continue;
        }
        $class = trim($parts[1], '\\');
        $methodName = $parts[2];
        if (!array_key_exists($class, $symbols)) {
            $failures[] = $pluginName . ': controller class missing for ' . $pair . ': ' . $class;
        } elseif (!isset($symbols[$class][$methodName])) {
            $failures[] = $pluginName . ': public handler missing for ' . $pair . ': ' . $handler;
        }
        $permission = trim((string) ($route['permission'] ?? ''));
        if ($permission !== '' && !isset($permissionKeys[$permission])) {
            $failures[] = $pluginName . ': route permission is undeclared for ' . $pair . ': ' . $permission;
        }
    }

    foreach (($manifest['migrations'] ?? []) as $migration) {
        $migration = str_replace('\\', '/', (string) $migration);
        if ($migration === '' || str_starts_with($migration, '/') || str_contains('/' . $migration . '/', '/../') || !is_file($pluginRoot . '/' . $migration)) {
            $failures[] = $pluginName . ': missing or unsafe migration: ' . $migration;
        }
    }
    foreach (($manifest['assets'] ?? []) as $asset) {
        $asset = str_replace('\\', '/', (string) $asset);
        if ($asset === '' || str_starts_with($asset, '/') || str_contains('/' . $asset . '/', '/../') || !is_file($pluginRoot . '/' . $asset)) {
            $failures[] = $pluginName . ': missing or unsafe asset: ' . $asset;
        }
    }
    foreach (($manifest['dependencies'] ?? []) as $dependency) {
        $dependencyId = is_array($dependency) ? trim((string) ($dependency['id'] ?? '')) : trim((string) $dependency);
        if ($dependencyId === '' || $dependencyId === ($manifest['id'] ?? '') || !isset($knownPluginIds[$dependencyId])) {
            $failures[] = $pluginName . ': missing or invalid plugin dependency: ' . ($dependencyId ?: '(empty)');
        }
    }
    $declaredConflicts = [];
    foreach ((array) ($manifest['conflicts_with'] ?? []) as $conflictId) {
        $conflictId = trim((string) $conflictId);
        if ($conflictId === '' || $conflictId === $manifestId || !isset($knownPluginIds[$conflictId])) {
            $failures[] = $pluginName . ': missing or invalid plugin conflict declaration: ' . ($conflictId ?: '(empty)');
        }
        if (isset($declaredConflicts[$conflictId])) {
            $failures[] = $pluginName . ': duplicate plugin conflict declaration: ' . $conflictId;
        }
        $declaredConflicts[$conflictId] = true;
    }
}

foreach ($allManifests as $leftId => $leftManifest) {
    foreach ($allManifests as $rightId => $rightManifest) {
        if (strcmp($leftId, $rightId) >= 0 || !PluginManager::routeManifestsConflict($leftManifest, $rightManifest)) {
            continue;
        }
        $leftDeclares = in_array($rightId, (array) ($leftManifest['conflicts_with'] ?? []), true);
        $rightDeclares = in_array($leftId, (array) ($rightManifest['conflicts_with'] ?? []), true);
        if (!$leftDeclares && !$rightDeclares) {
            $failures[] = 'undeclared cross-plugin route conflict: ' . $leftId . ' <-> ' . $rightId;
        }
    }
}

if (!PluginManager::routeManifestsConflict(
    ['routes' => [['path' => 'records/{recordId}', 'method' => 'GET']]],
    ['routes' => [['path' => 'records/export', 'method' => 'GET']]]
)) {
    $failures[] = 'route conflict detector missed overlapping variable and literal segments';
}
if (PluginManager::routeManifestsConflict(
    ['routes' => [['path' => 'records/{recordId}', 'method' => 'GET']]],
    ['routes' => [['path' => 'records/export', 'method' => 'POST']]]
)) {
    $failures[] = 'route conflict detector incorrectly treats different methods as conflicting';
}
$inventoryManifest = $allManifests['proma-inventory-partners'] ?? [];
$partnershipManifest = $allManifests['proma-partnership-ledger'] ?? [];
if (!$inventoryManifest || !$partnershipManifest
    || !PluginManager::pluginManifestsConflict($inventoryManifest, $partnershipManifest)) {
    $failures[] = 'the Inventory Partners and Partnership Ledger conflict is not enforced';
}
if ($inventoryManifest && $partnershipManifest
    && PluginManager::comparePluginBootPriority($inventoryManifest, $partnershipManifest) >= 0) {
    $failures[] = 'stable Inventory Partners must boot before the pre-release Partnership Ledger';
}
if (!preg_match('/^>=\s*7\.1\.0$/', (string) ($partnershipManifest['requires_core'] ?? ''))) {
    $failures[] = 'Partnership Ledger must require Core 7.1.0 or newer';
}

if ($manifestCount === 0) $failures[] = 'no plugin manifests found';
if ($failures) {
    fwrite(STDERR, "PLUGIN_MANIFEST_INTEGRITY_FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo 'PLUGIN_MANIFEST_INTEGRITY_OK: manifests=' . $manifestCount . ' routes=' . $routeCount . PHP_EOL;
