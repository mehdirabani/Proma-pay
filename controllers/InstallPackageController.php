<?php

class InstallPackageController extends Controller
{
    public function create()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $package = EasyInstallPackageService::create();
            set_flash('success', 'بسته نصبی آسان ساخته شد: ' . $package['name']);
        } catch (Throwable $e) {
            BackupService::log('easy_install_package', null, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('settings', ['tab' => 'update']);
    }

    public function download($fileName)
    {
        $this->requireRole('admin');
        $path = EasyInstallPackageService::find($fileName);
        if (!$path) {
            http_response_code(404);
            echo 'بسته نصبی پیدا نشد.';
            return;
        }
        header('Content-Type: application/zip');
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
            if (!ConfirmationCode::verify('easy_install_delete_' . sha1($decoded), $_POST['confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید حذف بسته نصبی درست وارد نشده است.');
            }
            EasyInstallPackageService::delete($decoded);
            set_flash('success', 'بسته نصبی حذف شد.');
        } catch (Throwable $e) {
            BackupService::log('easy_install_delete', $decoded, 'failed', $e->getMessage());
            set_flash('error', $e->getMessage());
        }
        redirect('settings', ['tab' => 'update']);
    }
}
