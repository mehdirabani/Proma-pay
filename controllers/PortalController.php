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
            'medals' => [],
            'givenGuarantees' => Contract::all(['guarantor_id' => Auth::id()]),
            'receivedGuarantees' => Model::fetchAll(
                "SELECT c.contract_number, c.id AS contract_id, u.full_name, u.mobile, u.national_id
                 FROM contracts c
                 JOIN contract_guarantors cg ON cg.contract_id = c.id
                 JOIN users u ON u.id = cg.guarantor_id
                 WHERE c.customer_id = ?
                 ORDER BY c.id DESC, u.full_name",
                [Auth::id()]
            ),
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
        $this->render('contracts/index', [
            'title' => 'قراردادهای ضمانت شده',
            'contracts' => Contract::all(['guarantor_id' => Auth::id(), 'search' => $_GET['q'] ?? null]),
            'customers' => [],
            'operators' => [],
            'settings' => Settings::allKeyed(),
            'readOnly' => true,
        ]);
    }
}
