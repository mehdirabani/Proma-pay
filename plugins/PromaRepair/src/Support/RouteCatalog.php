<?php

namespace Proma\Plugins\Repair\Support;

final class RouteCatalog
{
    private static ?array $routes = null;

    public static function all(): array
    {
        if (self::$routes !== null) {
            return self::$routes;
        }
        // Executable routes belong to the installed package, even when an older
        // Core still holds a previous manifest snapshot in its registry.
        $manifest = \PluginManifest::read(dirname(__DIR__, 2));
        $routes = [];
        foreach ($manifest['routes'] ?? [] as $route) {
            $route['method'] = strtoupper((string) ($route['method'] ?? 'GET'));
            $routes[$route['method'] . ' ' . $route['path']] = $route;
            if (strpos($route['path'], 'plugin/repair/') === 0) {
                $alias = $route;
                $alias['path'] = 'plugin/Repair/' . substr($route['path'], strlen('plugin/repair/'));
                $routes[$alias['method'] . ' ' . $alias['path']] = $alias;
            }
        }
        self::$routes = array_values($routes);
        usort(self::$routes, static function (array $a, array $b): int {
            return substr_count($a['path'], '{') <=> substr_count($b['path'], '{')
                ?: strlen($b['path']) <=> strlen($a['path']);
        });
        return self::$routes;
    }

    public static function resolve(string $path, string $method): ?array
    {
        $path = trim($path, '/');
        foreach (self::all() as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            $segments = preg_split('/(\{[a-zA-Z0-9_]+\})/', $route['path'], -1, PREG_SPLIT_DELIM_CAPTURE);
            $pattern = '';
            foreach ($segments as $segment) {
                $pattern .= preg_match('/^\{[a-zA-Z0-9_]+\}$/', $segment)
                    ? '([^/]+)' : preg_quote($segment, '~');
            }
            if (preg_match('~^' . $pattern . '$~D', $path, $matches)) {
                array_shift($matches);
                return ['route' => $route, 'parameters' => array_map('urldecode', $matches)];
            }
        }
        return null;
    }
}
