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
            $this->flashPluginError('plugin_upload', $e, 'بارگذاری پلاگین انجام نشد.');
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
            $this->flashPluginError('plugin_rescan', $e, 'بازبینی پوشه پلاگین‌ها انجام نشد.');
        }
        redirect('plugins');
    }

    public function repair($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('manage_plugins');
        $this->onlyPost();
        try {
            PluginReconciliationService::repair($pluginId, Auth::id());
            set_flash('success', 'رجیستری و فایل‌های افزونه همگام شدند. اکنون نصب افزونه را اجرا کنید.');
        } catch (Throwable $e) {
            $this->flashPluginError('plugin_repair', $e, 'تعمیر وضعیت افزونه انجام نشد.');
        }
        redirect('plugins');
    }

    public function clearStale($pluginId)
    {
        $this->requireRole('admin');
        PluginManager::requirePermission('manage_plugins');
        $this->onlyPost();
        try {
            PluginReconciliationService::clearStale($pluginId, Auth::id());
            set_flash('success', 'رکورد قدیمی افزونه از فهرست عملیاتی پاک شد.');
        } catch (Throwable $e) {
            $this->flashPluginError('plugin_clear_stale', $e, 'پاک‌سازی رکورد افزونه انجام نشد.');
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
            $this->flashPluginError('plugin_install', $e, 'نصب پلاگین انجام نشد.');
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
            $this->flashPluginError('plugin_activate', $e, 'فعال‌سازی پلاگین انجام نشد.');
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
            $this->flashPluginError('plugin_deactivate', $e, 'غیرفعال‌سازی پلاگین انجام نشد.');
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
            set_flash('success', 'پلاگین غیرفعال و فایل‌های آن از هاست حذف شدند؛ داده‌های افزونه حفظ شده‌اند و نصب مجدد ممکن است.');
        } catch (Throwable $e) {
            $this->flashPluginError('plugin_uninstall', $e, 'حذف پلاگین انجام نشد.');
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
                set_flash('error', 'بروزرسانی نصب شد، اما پاک‌سازی نسخه ایمنی فایل‌ها کامل نشد. شناسه پیگیری: ' . ErrorHandler::requestId());
            }
        } catch (Throwable $e) {
            $this->flashPluginError('plugin_update', $e, 'بروزرسانی افزونه کامل نشد. نسخه قبلی افزونه و اطلاعات حسابداری حفظ شده‌اند.');
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
            $this->flashPluginError('plugin_update_package', $e, 'آماده‌سازی بروزرسانی افزونه انجام نشد.');
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
                    ErrorHandler::log('plugin_delete_from_host_item:' . $pluginId, $e, 500);
                    $errors[] = $pluginId;
                }
            }
            if ($deleted) {
                set_flash('success', to_persian_digits(count($deleted)) . ' افزونه به‌طور کامل از پوشه plugins هاست حذف شد. داده‌های دیتابیس افزونه‌ها حفظ شده‌اند.');
            }
            if ($errors) {
                set_flash('error', 'حذف برخی افزونه‌ها انجام نشد. شناسه پیگیری: ' . ErrorHandler::requestId());
            }
        } catch (Throwable $e) {
            $this->flashPluginError('plugin_delete_from_host', $e, 'حذف افزونه‌ها از هاست انجام نشد.');
        }
        redirect('plugins');
    }

    private function flashPluginError($kind, Throwable $e, $message)
    {
        ErrorHandler::log((string) $kind, $e, 500);
        set_flash('error', (string) $message . ' شناسه پیگیری: ' . ErrorHandler::requestId());
    }
}
