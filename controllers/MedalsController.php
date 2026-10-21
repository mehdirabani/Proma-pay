<?php

class MedalsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        redirect('loyalty');
    }

    public function store()
    {
        $this->retiredWrite();
    }

    public function revoke($id)
    {
        $this->retiredWrite();
    }

    public function award($userId)
    {
        $this->retiredWrite();
    }

    public function restore($id)
    {
        $this->retiredWrite();
    }

    public function toggle($id)
    {
        $this->retiredWrite();
    }

    public function synchronize()
    {
        $this->retiredWrite();
    }

    protected function retiredWrite()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        set_flash('info', 'مدال‌ها اکنون بر پایه امتیاز فعالیت مشتری به‌صورت خودکار اعطا می‌شوند. تنظیمات را در باشگاه امتیاز مشتریان انجام دهید.');
        redirect('loyalty');
    }
}
