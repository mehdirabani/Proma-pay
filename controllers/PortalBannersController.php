<?php

class PortalBannersController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $this->render('settings/customer-banners', [
            'title' => 'مدیریت بنرهای پنل مشتری',
            'banners' => PortalBanner::allForAdmin(),
            'editingBanner' => $editId ? PortalBanner::find($editId) : null,
        ]);
    }

    public function save($id = null)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $id = $id !== null ? (int) $id : null;
        $existing = $id ? PortalBanner::find($id) : null;
        $desktopPath = $existing['desktop_image_path'] ?? null;
        $mobilePath = $existing['mobile_image_path'] ?? null;
        $newPaths = [];
        try {
            if (!empty($_FILES['desktop_image']) && (int) ($_FILES['desktop_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $desktopPath = UploadHelper::storePortalBannerImage($_FILES['desktop_image'], 'portal-banners/desktop');
                $newPaths[] = $desktopPath;
            }
            if (!empty($_FILES['mobile_image']) && (int) ($_FILES['mobile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $mobilePath = UploadHelper::storePortalBannerImage($_FILES['mobile_image'], 'portal-banners/mobile');
                $newPaths[] = $mobilePath;
            }
            if (!empty($_POST['remove_desktop_image'])) {
                $desktopPath = null;
            }
            if (!empty($_POST['remove_mobile_image'])) {
                $mobilePath = null;
            }
            $payload = $_POST;
            $payload['desktop_image_path'] = $desktopPath;
            $payload['mobile_image_path'] = $mobilePath;
            PortalBanner::save($payload, Auth::id(), $id);
            set_flash('success', $id ? 'بنر پنل مشتری ویرایش شد.' : 'بنر پنل مشتری ایجاد شد.');
        } catch (Throwable $e) {
            foreach (array_filter($newPaths) as $path) {
                try {
                    UploadHelper::deleteRelative($path);
                } catch (Throwable $cleanupError) {
                }
            }
            set_flash('error', $e->getMessage());
            redirect('portal-banners', $id ? ['edit' => $id] : []);
        }
        redirect('portal-banners');
    }

    public function toggle($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $active = PortalBanner::toggle((int) $id, Auth::id());
            set_flash('success', $active ? 'بنر فعال شد.' : 'بنر غیرفعال شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('portal-banners');
    }

    public function archive($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            PortalBanner::archive((int) $id, Auth::id());
            set_flash('success', 'بنر بایگانی شد و سابقه آن محفوظ ماند.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('portal-banners');
    }
}
