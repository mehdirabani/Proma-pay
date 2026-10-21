<?php

namespace Proma\Plugins\Repair\Controllers;

use Proma\Plugins\Repair\Repositories\RepairRepository;

final class CoreCompatibilityController extends \Controller
{
    public function createTechnician(): void
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            (new RepairRepository())->createTechnicianAccount($_POST, (int) \Auth::id());
            \set_flash('success', 'حساب تعمیرکار ایجاد شد. پروفایل کاری او در این صفحه قابل مدیریت است.');
        } catch (\InvalidArgumentException $e) {
            \ErrorHandler::abort(422, $e->getMessage());
        } catch (\Throwable $e) {
            \set_flash('error', 'ثبت تعمیرکار انجام نشد. مشخصات تکراری را بررسی کنید.');
        }
        \redirect('plugin/repair/technicians');
    }
}
