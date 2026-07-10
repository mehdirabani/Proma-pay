<?php

class FileManagerController extends Controller
{
    private const MAX_FILE_SIZE = 10485760;
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'rar'];

    public function index()
    {
        $this->requireRole('admin');
        $this->render('file-manager/index', [
            'title' => 'مدیریت فایل',
            'files' => $this->files(),
            'maxFileSize' => self::MAX_FILE_SIZE,
            'allowedExtensions' => self::ALLOWED_EXTENSIONS,
        ]);
    }

    public function upload()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $upload = $_FILES['managed_file'] ?? null;
            if (!$upload || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new InvalidArgumentException('ابتدا فایل را انتخاب کنید.');
            }
            if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                throw new InvalidArgumentException('فایل به‌درستی بارگذاری نشد.');
            }
            if ((int) ($upload['size'] ?? 0) > self::MAX_FILE_SIZE) {
                throw new InvalidArgumentException('حجم فایل باید حداکثر ۱۰ مگابایت باشد.');
            }

            $extension = strtolower(pathinfo($upload['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                throw new InvalidArgumentException('فرمت فایل مجاز نیست.');
            }

            $dir = $this->baseDir();
            $originalBase = pathinfo($upload['name'] ?? 'file', PATHINFO_FILENAME);
            $safeBase = $this->safeSegment($originalBase) ?: 'file';
            $fileName = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '-' . $safeBase . '.' . $extension;
            $destination = $dir . DIRECTORY_SEPARATOR . $fileName;
            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new RuntimeException('ذخیره فایل انجام نشد.');
            }

            set_flash('success', 'فایل با موفقیت اضافه شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('file-manager');
    }

    public function download($fileName)
    {
        $this->requireRole('admin');
        $path = $this->filePath((string) $fileName);
        if (!$path || !is_file($path)) {
            http_response_code(404);
            echo 'فایل پیدا نشد.';
            exit;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function delete($fileName)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $path = $this->filePath((string) $fileName);
        if ($path && is_file($path)) {
            @unlink($path);
            set_flash('success', 'فایل حذف شد.');
        } else {
            set_flash('error', 'فایل پیدا نشد.');
        }
        redirect('file-manager');
    }

    private function files()
    {
        $dir = $this->baseDir();
        $items = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $items[] = [
                'name' => basename($path),
                'size' => filesize($path),
                'modified_at' => filemtime($path),
                'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ];
        }
        usort($items, function ($a, $b) {
            return ($b['modified_at'] ?? 0) <=> ($a['modified_at'] ?? 0);
        });
        return $items;
    }

    private function baseDir()
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'file-manager';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function filePath($fileName)
    {
        $fileName = basename(rawurldecode((string) $fileName));
        if ($fileName === '' || $fileName !== $this->safeFileName($fileName)) {
            return null;
        }
        $base = realpath($this->baseDir());
        $path = $base . DIRECTORY_SEPARATOR . $fileName;
        $dir = realpath(dirname($path));
        if (!$base || !$dir || strpos($dir, $base) !== 0) {
            return null;
        }
        return $path;
    }

    private function safeFileName($value)
    {
        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        $base = pathinfo($value, PATHINFO_FILENAME);
        return $this->safeSegment($base) . ($extension !== '' ? '.' . $extension : '');
    }

    private function safeSegment($value)
    {
        $value = to_english_digits((string) $value);
        $value = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', $value);
        return trim((string) $value, '-_');
    }
}
