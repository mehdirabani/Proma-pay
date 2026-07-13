<?php

class UpdatesController extends Controller
{
    public function upload()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $name = ScriptUpdateService::upload($_FILES['update_file'] ?? null);
            set_flash('success', 'بسته بروزرسانی «' . $name . '» بارگذاری و اعتبارسنجی شد.');
        } catch (Throwable $e) {
            BackupService::log('update_upload', $_FILES['update_file']['name'] ?? null, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('settings', ['tab' => 'update']);
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
            set_flash(
                'success',
                'بروزرسانی نصب شد. بکاپ ایمنی: ' . $result['backup'] . '، فایل‌های نصب‌شده: ' . to_persian_digits(count($result['installed_files'])) . '.'
            );
        } catch (Throwable $e) {
            BackupService::log('update_install', $decoded, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('settings', ['tab' => 'update']);
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
        redirect('settings', ['tab' => 'update']);
    }
}
