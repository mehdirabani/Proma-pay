<?php

class LoyaltyController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('loyalty/index', [
            'title' => 'باشگاه امتیاز مشتریان',
            'rules' => CustomerLoyalty::rules(),
            'tiers' => CustomerLoyalty::tiers(false),
            'summary' => CustomerLoyalty::managementSummary(),
        ]);
    }

    public function storeRule()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                CustomerLoyalty::updateRule($id, $_POST['points'] ?? 0, to_english_digits($_POST['is_active'] ?? '') === '1');
                set_flash('success', 'امتیاز فعالیت به‌روزرسانی شد.');
            } else {
                CustomerLoyalty::createManualRule($_POST, Auth::id());
                set_flash('success', 'فعالیت جدید به فهرست امتیازها اضافه شد.');
            }
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ذخیره فعالیت امتیازی انجام نشد.');
        }
        redirect('loyalty');
    }

    public function toggleRule($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $active = CustomerLoyalty::toggleRule((int) $id);
            set_flash('success', $active ? 'فعالیت امتیازی فعال شد.' : 'فعالیت امتیازی غیرفعال شد؛ سوابق قبلی حفظ شده‌اند.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تغییر وضعیت فعالیت انجام نشد.');
        }
        redirect('loyalty');
    }

    public function storeTier()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            CustomerLoyalty::saveTier($_POST, Auth::id());
            set_flash('success', 'سطح و مدال آن ذخیره شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ذخیره سطح وفاداری انجام نشد.');
        }
        redirect('loyalty');
    }

    public function toggleTier($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $active = CustomerLoyalty::toggleTier((int) $id);
            set_flash('success', $active ? 'سطح وفاداری فعال شد.' : 'سطح وفاداری غیرفعال شد؛ مدال‌های قبلی مشتریان حفظ می‌شوند.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تغییر وضعیت سطح انجام نشد.');
        }
        redirect('loyalty');
    }
}
