<?php
namespace Proma\Plugins\SignConnect\Controllers;

use Proma\Plugins\SignConnect\Services\GeneralMessageService;

final class GeneralMessageController extends \Controller
{
    public function send(): void
    {
        $this->onlyPost();
        try {
            $result = (new GeneralMessageService())->send($_POST, (int) \Auth::id());
            \set_flash('success', (int)$result['queued'] . ' پیام در صف امن ارسال قرار گرفت.');
        } catch (\InvalidArgumentException $e) {
            \set_flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            \set_flash('error', 'قرار دادن پیام در صف انجام نشد. شناسه پیگیری: ' . \ErrorHandler::requestId());
        }
        \redirect('plugin/sign-connect/settings/compose');
    }
}
