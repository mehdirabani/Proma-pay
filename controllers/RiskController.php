<?php

class RiskController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['per_page'] ?? 25);
        if (!in_array($perPage, [10, 25, 50], true)) $perPage = 25;
        $needle = '%' . to_english_digits($query) . '%';
        $where = "role='customer' AND status='active'
             AND (? = '' OR full_name LIKE ? OR mobile LIKE ? OR national_id LIKE ?)";
        $params = [$query, $needle, $needle, $needle];
        $total = (int) (Model::fetch("SELECT COUNT(*) AS total FROM users WHERE {$where}", $params)['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $customers = Model::fetchAll(
            "SELECT id, full_name, mobile, status FROM users WHERE {$where}
             ORDER BY id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );
        try { $profiles = CreditRiskEngine::summariesForCustomers(array_column($customers, 'id')); }
        catch (Throwable $e) { $profiles = []; if (class_exists('ErrorHandler')) ErrorHandler::log('risk_profiles_list', $e, 500); }
        foreach ($customers as &$customer) $customer['credit_profile'] = $profiles[(int) $customer['id']] ?? null;
        unset($customer);
        $summary = ['low' => 0, 'medium' => 0, 'high' => 0, 'pending' => 0, 'average' => 0];
        $scores = [];
        foreach ($customers as $customer) {
            $profile = $customer['credit_profile'] ?? null;
            if (!$profile) {
                $summary['pending']++;
                continue;
            }
            $level = (string) ($profile['risk_level'] ?? 'high');
            if (!array_key_exists($level, $summary)) $level = 'high';
            $summary[$level]++;
            $scores[] = (int) ($profile['score'] ?? 0);
        }
        $summary['average'] = $scores ? (int) round(array_sum($scores) / count($scores)) : 0;
        $this->render('risk/index', [
            'title' => 'پروفایل ریسک اعتباری', 'customers' => $customers, 'query' => $query,
            'summary' => $summary, 'pagination' => ['total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage],
        ]);
    }

    public function summary($customerId)
    {
        $this->requireRole('admin');
        try {
            $profile = CreditRiskEngine::summaryForCustomer((int) $customerId, 300);
            $this->json(['ok' => true, 'summary' => [
                'score' => (int) ($profile['score'] ?? 0),
                'risk_level' => (string) ($profile['risk_level'] ?? 'high'),
                'recommendation' => $profile['recommendation'] ?? [],
                'calculated_at' => (string) ($profile['calculated_at'] ?? date('Y-m-d H:i:s')),
            ]]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'محاسبهٔ رتبهٔ اعتباری انجام نشد.'], 422);
        }
    }

    public function profile($customerId)
    {
        $this->requireRole('admin');
        try {
            $profile = CreditRiskEngine::profile((int) $customerId);
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
            redirect('risk');
        }
        $this->render('risk/profile', ['title' => 'Proma Score مشتری', 'profile' => $profile]);
    }
}
