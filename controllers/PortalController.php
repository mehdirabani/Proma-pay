<?php

class PortalController extends Controller
{
    public function index()
    {
        $this->requireRole('customer');
        redirect('dashboard');
    }

    public function contracts()
    {
        $this->requireRole('customer');
        $this->render('dashboard/customer', [
            'title' => 'قراردادهای من',
            'contracts' => Contract::all(['customer_id' => Auth::id()]),
            'installments' => [],
            'payments' => Payment::recentForCustomer(Auth::id(), 9),
            'medals' => [],
        ]);
    }

    public function installments()
    {
        $this->requireRole('customer');
        $this->render('installments/index', [
            'title' => 'اقساط من',
            'installments' => Installment::all(['customer_id' => Auth::id()]),
            'contracts' => [],
            'customerMode' => true,
        ]);
    }

    public function guaranteed()
    {
        $this->requireRole('customer');
        $this->render('portal/guaranteed', [
            'title' => 'ضمانت‌ها',
            'contracts' => Contract::all(['guarantor_id' => Auth::id()]),
            'guarantors' => Contract::guarantorsForCustomer(Auth::id()),
        ]);
    }
}
