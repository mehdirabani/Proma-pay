<?php

class PluginsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('manage_plugins');
        $this->render('plugins/index', [
            'title' => 'پلاگین‌ها',
            'plugins' => PluginManager::discover(),
            'pluginApiVersion' => plugin_api_version(),
        ]);
    }

    public function upload()
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('install_plugins');
        $this->onlyPost();
        try {
            $manifest = PluginManager::instance()->upload($_FILES['plugin_zip'] ?? [], Auth::id());
            set_flash('success', 'پلاگین «' . ($manifest['name'] ?? $manifest['id']) . '» با موفقیت بررسی و بارگذاری شد. اکنون آن را نصب کنید.');
        } catch (Throwable $e) {
            set_flash('error', 'بارگذاری پلاگین انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }

    public function rescan()
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('manage_plugins');
        $this->onlyPost();
        try {
            $plugins = PluginManager::instance()->rescan(Auth::id());
            set_flash('success', 'پوشه پلاگین‌ها بررسی شد و وضعیت ' . to_persian_digits(count($plugins)) . ' افزونه همگام شد.');
        } catch (Throwable $e) {
            set_flash('error', 'بازبینی پوشه پلاگین‌ها انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }

    public function install($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('install_plugins');
        $this->onlyPost();
        try {
            PluginManager::instance()->install($pluginId, Auth::id());
            set_flash('success', 'پلاگین نصب شد. برای اجرای قابلیت‌ها آن را فعال کنید.');
        } catch (Throwable $e) {
            set_flash('error', 'نصب پلاگین انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }

    public function activate($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('activate_plugins');
        $this->onlyPost();
        try {
            PluginManager::instance()->activate($pluginId, Auth::id());
            set_flash('success', 'پلاگین فعال شد.');
        } catch (Throwable $e) {
            set_flash('error', 'فعال‌سازی پلاگین انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }

    public function deactivate($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('deactivate_plugins');
        $this->onlyPost();
        try {
            PluginManager::instance()->deactivate($pluginId, Auth::id());
            set_flash('success', 'پلاگین غیرفعال شد و داده‌های آن حفظ شدند.');
        } catch (Throwable $e) {
            set_flash('error', 'غیرفعال‌سازی پلاگین انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }

    public function uninstall($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('uninstall_plugins');
        $this->onlyPost();
        try {
            PluginManager::instance()->uninstall($pluginId, Auth::id());
            set_flash('success', 'پلاگین غیرفعال و ثبت آن حذف شد؛ داده‌های افزونه حفظ شده‌اند.');
        } catch (Throwable $e) {
            set_flash('error', 'حذف پلاگین انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }

    public function health($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('manage_plugins');
        $result = PluginManager::instance()->healthCheck($pluginId);
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('plugins');
    }

    public function update($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('update_plugins');
        $this->onlyPost();
        try {
            if (!ConfirmationCode::verify('plugin_update_apply_' . sha1((string) $pluginId), $_POST['confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید نصب بروزرسانی افزونه درست نیست.');
            }
            $result = PluginManager::instance()->update($pluginId, Auth::id());
            set_flash('success', 'افزونه از نسخه ' . ($result['from'] ?? '-') . ' به نسخه ' . ($result['to'] ?? '-') . ' بروزرسانی شد.');
            if (!empty($result['cleanup_warning'])) {
                set_flash('error', 'بروزرسانی نصب شد، اما پاک‌سازی نسخه ایمنی فایل‌ها کامل نشد: ' . $result['cleanup_warning']);
            }
        } catch (Throwable $e) {
            set_flash('error', 'به‌روزرسانی افزونه انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }

    public function updatePackage($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('update_plugins');
        $this->onlyPost();
        try {
            if (!ConfirmationCode::verify('plugin_update_stage_' . sha1((string) $pluginId), $_POST['confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید بارگذاری نسخه جدید افزونه درست نیست.');
            }
            $result = PluginManager::instance()->stageUpdate($pluginId, $_FILES['plugin_update_zip'] ?? [], Auth::id());
            set_flash('success', 'نسخه ' . ($result['to'] ?? '-') . ' برای افزونه «' . ($result['name'] ?? $pluginId) . '» آماده شد. اکنون نصب بروزرسانی را تایید کنید.');
        } catch (Throwable $e) {
            set_flash('error', 'آماده‌سازی بروزرسانی افزونه انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }

    public function deleteFromHost()
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('uninstall_plugins');
        $this->onlyPost();
        try {
            if (!ConfirmationCode::verify('plugins_delete_from_host', $_POST['confirm_text'] ?? '')) {
                throw new RuntimeException('عدد تایید حذف کامل افزونه‌ها درست نیست.');
            }
            $pluginIds = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['plugin_ids'] ?? [])), static function ($id) {
                return preg_match('/^[a-z][a-z0-9._-]{2,99}$/', (string) $id);
            })));
            if (!$pluginIds || count($pluginIds) > 50) {
                throw new InvalidArgumentException('حداقل یک و حداکثر ۵۰ افزونه معتبر انتخاب کنید.');
            }
            $deleted = [];
            $errors = [];
            foreach ($pluginIds as $pluginId) {
                try {
                    $deleted[] = PluginManager::instance()->deleteFromHost($pluginId, Auth::id());
                } catch (Throwable $e) {
                    $errors[] = $pluginId . ': ' . $e->getMessage();
                }
            }
            if ($deleted) {
                set_flash('success', to_persian_digits(count($deleted)) . ' افزونه به‌طور کامل از پوشه plugins هاست حذف شد. داده‌های دیتابیس افزونه‌ها حفظ شده‌اند.');
            }
            if ($errors) {
                set_flash('error', 'حذف برخی افزونه‌ها انجام نشد: ' . implode(' | ', $errors));
            }
        } catch (Throwable $e) {
            set_flash('error', 'حذف افزونه‌ها از هاست انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }
}
