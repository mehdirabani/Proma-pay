<?php

class BackupController extends Controller
{
    public function data()
    {
        $this->requireRole('admin');
        header('Cache-Control: private, no-store');
        $tables = Model::db()->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        sort($tables, SORT_STRING);
        $table = (string) ($_GET['table'] ?? 'contracts');
        if (!in_array($table, $tables, true)) { ErrorHandler::abort(404); return; }
        $quoted = '`' . str_replace('`', '``', $table) . '`';
        $columns = Model::db()->query('SHOW COLUMNS FROM ' . $quoted)->fetchAll(PDO::FETCH_ASSOC);
        $total = (int) Model::db()->query('SELECT COUNT(*) FROM ' . $quoted)->fetchColumn();
        $pages = max(1, (int) ceil($total / 25));
        $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
        $keys = Model::db()->query('SHOW INDEX FROM ' . $quoted . " WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
        $order = $keys ? implode(',', array_map(static fn($key) => '`' . str_replace('`', '``', $key['Column_name']) . '`', $keys)) : '`' . str_replace('`', '``', $columns[0]['Field']) . '`';
        $rows = Model::fetchAll('SELECT * FROM ' . $quoted . ' ORDER BY ' . $order . ' LIMIT 25 OFFSET ' . (($page - 1) * 25));
        $this->render('backup/data', compact('tables', 'table', 'columns', 'total', 'pages', 'page', 'rows'));
    }

    public function original($hash)
    {
        $this->requireRole('admin');
        if (!preg_match('/\A[a-f0-9]{64}\z/', (string) $hash)) { ErrorHandler::abort(404); return; }
        $path = dirname(__DIR__) . '/storage/restore-archives/' . $hash . '/original-backup.zip';
        if (!is_file($path) || is_link($path) || !hash_equals($hash, hash_file('sha256', $path))) { ErrorHandler::abort(404); return; }
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="proma-pay-original-backup-' . substr($hash, 0, 12) . '.zip"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

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
            if (BackupService::restoreRequestPayloadExceeded()) {
                $limits = BackupService::restoreUploadLimits();
                throw new RuntimeException('اندازه درخواست از post_max_size هاست (' . $limits['post_limit']
                    . ') بیشتر است؛ سقف مؤثر فایل بکاپ در این فرم '
                    . to_persian_digits(number_format($limits['max_bytes'] / 1048576, 1))
                    . ' مگابایت است. post_max_size را افزایش دهید یا فایل کوچک‌تری انتخاب کنید.');
            }
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
