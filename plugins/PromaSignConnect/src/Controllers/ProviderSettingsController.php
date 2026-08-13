<?php
namespace Proma\Plugins\SignConnect\Controllers;

use Proma\Plugins\SignConnect\Services\ProviderEventMappingService;

final class ProviderSettingsController extends \Controller
{
    public function saveMappings($provider): void
    {
        $this->onlyPost();
        try {
            $count = (new ProviderEventMappingService())->save(
                (string) $provider,
                is_array($_POST['events'] ?? null) ? $_POST['events'] : [],
                (int) \Auth::id()
            );
            \set_flash('success', $count . ' نگاشت رویداد ذخیره شد.');
        } catch (\InvalidArgumentException $e) {
            \set_flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            \set_flash('error', 'ذخیره تنظیمات رویداد انجام نشد. شناسه پیگیری: ' . \ErrorHandler::requestId());
        }
        \redirect('plugin/sign-connect/settings/' . rawurlencode((string) $provider));
    }
}

