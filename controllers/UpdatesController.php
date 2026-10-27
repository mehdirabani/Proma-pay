<?php

class UpdatesController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('updates/index', [
            'updatePackages' => ScriptUpdateService::packages(),
            'updateUploadLimits' => ScriptUpdateService::uploadLimits(),
        ]);
    }

    public function upload()
    {
        $this->requireRole('admin');
        if (!is_post()) {
            ErrorHandler::abort(405);
        }
        // When post_max_size is exceeded PHP empties both $_POST and $_FILES,
        // so CSRF verification would otherwise turn this into an opaque 419.
        if (ScriptUpdateService::postLimitExceeded($_SERVER['CONTENT_LENGTH'] ?? '')) {
            $limits = ScriptUpdateService::uploadLimits();
            $this->flashSafely('error', 'درخواست آپلود به سقف post_max_size هاست (' . $limits['post_limit'] . ') رسیده است. سقف موثر فایل در این صفحه ' . to_persian_digits(number_format($limits['max_bytes'] / (1024 * 1024), 1)) . ' مگابایت است؛ مقدار post_max_size را در تنظیمات PHP هاست افزایش دهید یا فایل آپدیت کوچک‌تر را انتخاب کنید.');
            redirect('settings', ['tab' => 'update']);
        }
        $this->onlyPost();
        try {
            $name = ScriptUpdateService::upload($_FILES['update_file'] ?? null);
            $this->flashSafely('success', 'بسته بروزرسانی «' . $name . '» بارگذاری و اعتبارسنجی شد.');
        } catch (Throwable $e) {
            try {
                BackupService::log('update_upload', $_FILES['update_file']['name'] ?? null, 'failed', $e->getMessage());
            } catch (Throwable $logError) {
                error_log('[PromaPay][update_upload] Upload failed and audit logging failed: ' . $logError->getMessage());
            }
            $this->flashSafely('error', $e->getMessage());
        }
        redirect('updates');
    }

    /** A saved package must remain reachable even if the session cannot store a flash message. */
    private function flashSafely($type, $message)
    {
        try {
            set_flash($type, $message);
        } catch (Throwable $e) {
            error_log('[PromaPay][updates] Package flow completed but flash message could not be stored: ' . $e->getMessage());
        }
    }

    public function install($fileName)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $decoded = rawurldecode((string) $fileName);
        try {
            if (!ConfirmationCode::verify('update_install_' . sha1($decoded), $_POST['update_confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید نصب بروزرسانی درست وارد نشده است.');
            }
            $result = ScriptUpdateService::install($decoded);
            $this->flashSafely(
                'success',
                'بروزرسانی نصب شد. بکاپ ایمنی: ' . $result['backup']
                . '، فایل‌های نصب‌شده: ' . to_persian_digits(count($result['installed_files']))
                . '، فایل‌های قدیمی بازنشسته و پاک‌شده: ' . to_persian_digits(count($result['retired_files'] ?? [])) . '.'
            );
        } catch (Throwable $e) {
            BackupService::log('update_install', $decoded, 'failed', $e->getMessage());
            $this->flashSafely('error', $e->getMessage());
        }
        redirect('updates');
    }

    public function delete($fileName)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $decoded = rawurldecode((string) $fileName);
        try {
            if (!ConfirmationCode::verify('update_delete_' . sha1($decoded), $_POST['confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید حذف بسته بروزرسانی درست وارد نشده است.');
            }
            $deleted = ScriptUpdateService::delete($decoded);
            set_flash('success', 'بسته بروزرسانی «' . $deleted . '» حذف شد.');
        } catch (Throwable $e) {
            BackupService::log('update_delete', $decoded, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('updates');
    }
}
