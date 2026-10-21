<?php
namespace Proma\Plugins\SignConnect\Controllers;

use Proma\Plugins\SignConnect\Services\MessageTemplateService;

final class TemplateController extends \Controller
{
    public function save(): void
    {
        $this->onlyPost();
        try {
            (new MessageTemplateService())->save($_POST, (int) \Auth::id());
            \set_flash('success', 'نسخه جدید قالب ذخیره شد.');
        } catch (\InvalidArgumentException $e) {
            \set_flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            \set_flash('error', 'ذخیره قالب انجام نشد. شناسه پیگیری: ' . \ErrorHandler::requestId());
        }
        \redirect('plugin/sign-connect/settings/templates');
    }
}

