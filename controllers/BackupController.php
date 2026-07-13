<?php

class BackupController extends Controller
{
    public function create()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $backup = BackupService::create();
            set_flash('success', ($backup['type'] ?? 'zip') === 'sql' ? 'ZipArchive فعال نیست؛ بکاپ دیتابیس به صورت SQL ساخته شد.' : 'بکاپ با موفقیت ساخته شد.');
            redirect('backup/download/' . rawurlencode($backup['name']));
        } catch (Throwable $e) {
            BackupService::log('backup', null, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
            redirect('settings', ['tab' => 'backup']);
        }
    }

    public function restore()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            if (!ConfirmationCode::verify('backup_restore_upload', $_POST['restore_confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید بازیابی درست وارد نشده است.');
            }
            $safety = BackupService::restore($_FILES['backup_file'] ?? null);
            set_flash('success', 'بازیابی انجام شد. بکاپ ایمنی قبل از بازیابی: ' . $safety);
        } catch (Throwable $e) {
            BackupService::log('restore', $_FILES['backup_file']['name'] ?? null, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('settings', ['tab' => 'backup']);
    }

    public function restoreStored($fileName)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $decoded = rawurldecode((string) $fileName);
        try {
            if (!ConfirmationCode::verify('backup_restore_stored_' . sha1($decoded), $_POST['restore_confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید بازیابی درست وارد نشده است.');
            }
            $safety = BackupService::restoreStored($decoded);
            set_flash('success', 'بازیابی بکاپ ذخیره‌شده انجام شد. بکاپ ایمنی قبل از بازیابی: ' . $safety);
        } catch (Throwable $e) {
            BackupService::log('restore', $decoded, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('settings', ['tab' => 'backup']);
    }

    public function download($fileName)
    {
        $this->requireRole('admin');
        $path = BackupService::findBackup(rawurldecode((string) $fileName));
        if (!$path) {
            http_response_code(404);
            echo 'فایل بکاپ پیدا نشد.';
            return;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($extension === 'sql' ? 'application/sql; charset=utf-8' : 'application/zip'));
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function delete($fileName)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $decoded = rawurldecode((string) $fileName);
        try {
            if (!ConfirmationCode::verify('backup_delete_' . sha1($decoded), $_POST['confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید حذف بکاپ درست وارد نشده است.');
            }
            $deleted = BackupService::delete($decoded);
            set_flash('success', 'بکاپ «' . $deleted . '» حذف شد.');
        } catch (Throwable $e) {
            BackupService::log('backup_delete', $decoded, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('settings', ['tab' => 'backup']);
    }

    public function deleteLogs()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            if (!ConfirmationCode::verify('backup_logs_delete', $_POST['confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید حذف لاگ‌های بکاپ درست نیست.');
            }
            $scope = (string) ($_POST['delete_scope'] ?? 'selected');
            $count = $scope === 'all'
                ? BackupService::clearLogs()
                : BackupService::deleteLogs((array) ($_POST['log_ids'] ?? []));
            try {
                AuditLog::record('maintenance', 'backup_logs_deleted', 'backup_log', 0, [
                    'actor_user_id' => Auth::id(),
                    'severity' => 'high',
                    'description' => 'حذف لاگ‌های بخش بکاپ توسط مدیر',
                    'new_values' => ['scope' => $scope, 'deleted_count' => (int) $count],
                ]);
            } catch (Throwable $ignored) {
            }
            set_flash('success', to_persian_digits((int) $count) . ' لاگ از بخش بکاپ حذف شد.');
        } catch (Throwable $e) {
            set_flash('error', 'حذف لاگ‌های بکاپ انجام نشد: ' . $e->getMessage());
        }
        redirect('settings', ['tab' => 'backup']);
    }
}
