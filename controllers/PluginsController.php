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
            PluginManager::instance()->update($pluginId, Auth::id());
            set_flash('success', 'افزونه با migrationهای جدید به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', 'به‌روزرسانی افزونه انجام نشد: ' . $e->getMessage());
        }
        redirect('plugins');
    }
}
