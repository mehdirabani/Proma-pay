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
            'socialLinks' => configured_social_links(Settings::allKeyed()),
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
        redirect('installments/panel');
    }

    public function history()
    {
        $this->requireRole('customer');
        $customerId = Auth::id();
        $contracts = Contract::all(['customer_id' => $customerId]);
        $contractIds = array_map('intval', array_column($contracts, 'id'));
        $statusCounts = [];
        foreach ($contracts as $contract) {
            $status = $contract['status'] ?? 'unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        $itemCount = 0;
        if ($contractIds) {
            try {
                $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
                $itemCount = (int) (Model::fetch("SELECT COUNT(*) AS total FROM contract_items WHERE contract_id IN ({$placeholders})", $contractIds)['total'] ?? 0);
            } catch (Throwable $e) {
                $itemCount = 0;
            }
        }
        $this->render('portal/history', [
            'title' => 'سوابق خرید',
            'contracts' => $contracts,
            'payments' => Payment::recentForCustomer($customerId, 30),
            'timeline' => Installment::all(['customer_id' => $customerId]),
            'summary' => [
                'contracts' => count($contracts),
                'purchases' => $itemCount ?: count($contracts),
                'last_purchase' => $contracts[0] ?? null,
                'statuses' => $statusCounts,
            ],
        ]);
    }

    public function guaranteed()
    {
        $this->requireRole('customer');
        $result = Contract::paginated([
            'guarantor_id' => Auth::id(),
            'search' => $_GET['q'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'per_page' => 24,
        ]);
        $this->render('contracts/index', [
            'title' => 'قراردادهای ضمانت شده',
            'contracts' => $result['items'],
            'pagination' => $result,
            'customers' => [],
            'operators' => [],
            'settings' => Settings::allKeyed(),
            'readOnly' => true,
        ], is_ajax_request() ? null : 'app');
    }
}
