<?php

class DesignSystemController extends Controller
{
    public function index()
    {
        Auth::requireRole(['admin']);

        $catalog = require __DIR__ . '/../config/design_system_catalog.php';
        $icons = [
            'feather' => $this->namesFromFiles(__DIR__ . '/../html/RTL/assets/fonts/feather/*.svg'),
            'fontawesome' => $this->namesFromStylesheet(
                __DIR__ . '/../html/RTL/assets/css/font-awesome.css',
                'fa'
            ),
            'themify' => $this->namesFromStylesheet(
                __DIR__ . '/../html/RTL/assets/css/vendors/themify.css',
                'ti'
            ),
            'proma' => $this->customIconNames(),
        ];
        $catalog['icons'] = $icons;
        $catalog['promaSvg'] = [];
        foreach ($icons['proma'] as $iconName) {
            $catalog['promaSvg'][$iconName] = proma_icon($iconName, '', 'ppds-custom-icon');
        }
        $catalog['counts']['icons'] = array_sum(array_map('count', $icons));

        $version = require __DIR__ . '/../config/version.php';
        $this->render('design-system/index', [
            'designSystemCatalog' => $catalog,
            'designSystemVersion' => (string) ($version['display'] ?? ('V' . ($version['application'] ?? 'current'))),
            'designSystemLanguages' => $catalog['languages'],
            'designSystemJsonFlags' => JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE,
            'designSystemTemplateUrl' => static function (string $path): string {
                return template_asset_url($path);
            },
            'designSystemAssetUrl' => static function (string $path): string {
                return asset_url($path);
            },
        ], null);
    }

    private function namesFromFiles(string $pattern): array
    {
        $files = glob($pattern) ?: [];
        $names = array_map(static function ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values(array_unique($names));
    }

    private function namesFromStylesheet(string $path, string $prefix): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $css = file_get_contents($path);
        if (!is_string($css)) {
            return [];
        }

        $escapedPrefix = preg_quote($prefix, '~');
        $pattern = '~\.' . $escapedPrefix . '-([a-z0-9-]+):before\s*\{[^}]*?content\s*:\s*["\']\\\\[a-f0-9]+~i';
        preg_match_all($pattern, $css, $matches);
        $names = array_values(array_unique($matches[1] ?? []));
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }

    private function customIconNames(): array
    {
        $path = __DIR__ . '/../helpers/functions.php';
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $source = file_get_contents($path);
        if (!is_string($source) || !preg_match('/function\s+proma_icon\s*\([^)]*\).*?\$icons\s*=\s*\[(.*?)\n\s*\];/s', $source, $block)) {
            return [];
        }

        preg_match_all("~'([a-z0-9-]+)'\\s*=>\\s*'(<(?:path|circle|rect|line|polyline))~i", $block[1], $matches);
        $names = array_values(array_unique($matches[1] ?? []));
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }
}
