<?php

class ManifestController extends Controller
{
    public function index()
    {
        $settings = Settings::allKeyed();
        $systemName = trim((string) ($settings['system_name'] ?? app_config('app_name', 'پروما'))) ?: 'پروما';
        $shortName = trim((string) ($settings['logo_text'] ?? $systemName)) ?: $systemName;
        $iconPath = trim((string) ($settings['logo_icon_path'] ?? ''));
        $faviconPath = trim((string) ($settings['favicon_path'] ?? ''));
        $manifestIcon = $iconPath ?: $faviconPath;
        $icons = [];

        if ($manifestIcon !== '') {
            $icons[] = [
                'src' => $this->absoluteAssetUrl($manifestIcon),
                'sizes' => '192x192',
                'type' => $this->mimeForPath($manifestIcon),
                'purpose' => 'any maskable',
            ];
            $icons[] = [
                'src' => $this->absoluteAssetUrl($manifestIcon),
                'sizes' => '512x512',
                'type' => $this->mimeForPath($manifestIcon),
                'purpose' => 'any maskable',
            ];
        }

        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode([
            'name' => $systemName,
            'short_name' => $shortName,
            'version' => app_version_label(),
            'description' => 'سامانه مدیریت قرارداد، اقساط و پرداخت',
            'start_url' => rtrim(detected_base_url(), '/') . '/index.php',
            'display' => 'standalone',
            'dir' => 'rtl',
            'lang' => 'fa',
            'background_color' => '#f4f7f6',
            'theme_color' => '#7366ff',
            'icons' => $icons,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    protected function absoluteAssetUrl($path)
    {
        $path = ltrim((string) $path, '/');
        $base = rtrim(detected_base_url(), '/');
        return ($base !== '' ? $base : app_base_url()) . '/' . $path;
    }

    protected function mimeForPath($path)
    {
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $types = [
            'ico' => 'image/x-icon',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];
        return $types[$extension] ?? 'image/png';
    }
}
