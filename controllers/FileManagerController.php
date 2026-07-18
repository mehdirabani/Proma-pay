<?php

class FileManagerController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'category' => trim((string) ($_GET['category'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'visibility' => trim((string) ($_GET['visibility'] ?? '')),
            'page' => max(1, (int) to_english_digits($_GET['page'] ?? 1)),
            'per_page' => 25,
        ];
        $registryReady = FileRecord::isAvailable();
        $this->render('file-manager/index', [
            'title' => 'مدیریت فایل‌ها',
            'registryReady' => $registryReady,
            'filters' => $filters,
            'filesPage' => $registryReady ? FileRecord::page($filters) : ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25],
            'stats' => $registryReady ? FileRecord::stats() : ['total' => 0, 'total_size' => 0, 'active' => 0, 'archived' => 0, 'deleted' => 0, 'recent' => 0],
            'categories' => $registryReady ? FileRecord::categories() : [],
            'maxFileSize' => FileRecord::MAX_FILE_SIZE,
            'allowedExtensions' => FileRecord::managedExtensions(),
        ]);
    }

    public function upload()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            FileRecord::storeManagedUpload($_FILES['managed_file'] ?? [], [
                'uploader_user_id' => Auth::id(),
                'uploader_role' => Auth::role(),
                'display_name' => $_POST['display_name'] ?? '',
                'category' => $_POST['category'] ?? 'general',
                'visibility' => $_POST['visibility'] ?? 'private',
                'description' => $_POST['description'] ?? '',
                'tags' => $_POST['tags'] ?? '',
                'source' => 'file_manager',
            ]);
            set_flash('success', 'فایل در فضای امن و فهرست مرکزی ثبت شد.');
        } catch (Throwable $e) {
            ErrorHandler::log('file_manager_upload', $e, 500);
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'بارگذاری فایل انجام نشد. migration مدیریت فایل را بررسی کنید.');
        }
        redirect('file-manager');
    }

    public function download($uuid)
    {
        $this->requireRole('admin');
        $file = FileRecord::findByUuid((string) $uuid);
        if (!$file || ($file['status'] ?? '') === 'deleted') {
            http_response_code(404);
            echo 'فایل پیدا نشد.';
            return;
        }
        $path = FileRecord::absoluteStoragePath($file['storage_path'] ?? '');
        if (!$path || !is_file($path)) {
            http_response_code(404);
            echo 'محتوای فایل در فضای ذخیره‌سازی پیدا نشد.';
            return;
        }
        $name = trim(preg_replace('/[\r\n"]+/', '', (string) ($file['original_name'] ?: $file['display_name'])));
        $name = $name ?: 'download';
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    public function update($uuid)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            FileRecord::updateMetadata((string) $uuid, $_POST, Auth::id());
            set_flash('success', 'اطلاعات فایل به‌روزرسانی شد. محتوای نسخه فعلی تغییر نکرد.');
        } catch (Throwable $e) {
            ErrorHandler::log('file_manager_metadata', $e, 500);
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ویرایش اطلاعات فایل انجام نشد.');
        }
        redirect('file-manager');
    }

    public function replace($uuid)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            FileRecord::replace((string) $uuid, $_FILES['replacement_file'] ?? [], Auth::id(), $_POST['replacement_reason'] ?? '');
            set_flash('success', 'نسخه جدید ثبت شد و نسخه قبلی بایگانی شد.');
        } catch (Throwable $e) {
            ErrorHandler::log('file_manager_replace', $e, 500);
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'جایگزینی نسخه فایل انجام نشد.');
        }
        redirect('file-manager');
    }

    public function relate($uuid)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $file = FileRecord::findByUuid((string) $uuid);
            if (!$file) {
                throw new InvalidArgumentException('فایل انتخاب‌شده پیدا نشد.');
            }
            FileRecord::addRelation(
                (int) $file['id'],
                $_POST['entity_type'] ?? '',
                (int) ($_POST['entity_id'] ?? 0),
                $_POST['relation_type'] ?? 'attachment',
                Auth::id()
            );
            set_flash('success', 'ارتباط فایل با رکورد انتخاب‌شده ثبت شد.');
        } catch (Throwable $e) {
            ErrorHandler::log('file_manager_relation', $e, 500);
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت ارتباط فایل انجام نشد.');
        }
        redirect('file-manager');
    }

    public function archive($uuid)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            FileRecord::archive((string) $uuid, Auth::id(), $_POST['reason'] ?? '');
            set_flash('success', 'فایل بایگانی شد و از بین نرفت.');
        } catch (Throwable $e) {
            ErrorHandler::log('file_manager_archive', $e, 500);
            set_flash('error', 'بایگانی فایل انجام نشد.');
        }
        redirect('file-manager');
    }

    public function restore($uuid)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            FileRecord::restore((string) $uuid, Auth::id(), $_POST['reason'] ?? '');
            set_flash('success', 'فایل بازیابی شد.');
        } catch (Throwable $e) {
            ErrorHandler::log('file_manager_restore', $e, 500);
            set_flash('error', 'بازیابی فایل انجام نشد.');
        }
        redirect('file-manager');
    }

    public function delete($uuid)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            FileRecord::softDelete((string) $uuid, Auth::id(), $_POST['deletion_reason'] ?? '');
            set_flash('success', 'فایل به‌صورت نرم حذف شد؛ سابقه و امکان ممیزی حفظ می‌شود.');
        } catch (Throwable $e) {
            ErrorHandler::log('file_manager_delete', $e, 500);
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'حذف فایل انجام نشد.');
        }
        redirect('file-manager');
    }

    public function sync()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $count = FileRecord::backfillStorage(200);
            set_flash('success', 'همگام‌سازی فایل‌ها انجام شد: ' . to_persian_digits($count) . ' فایل جدید ثبت شد.');
        } catch (Throwable $e) {
            ErrorHandler::log('file_manager_sync', $e, 500);
            set_flash('error', 'همگام‌سازی فایل‌های قدیمی انجام نشد.');
        }
        redirect('file-manager');
    }
}
