<?php

namespace Proma\Plugins\Zarinpal\Controllers;

use Proma\Plugins\Zarinpal\Repositories\ZarinpalTransactionRepository;
use Proma\Plugins\Zarinpal\Repositories\ZarinpalLogRepository;
use Proma\Plugins\Zarinpal\Services\ZarinpalReconciliationService;

class TransactionsController extends \Controller
{
    public function index()
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => preg_replace('/[^a-z_]/', '', (string) ($_GET['status'] ?? '')),
            'environment' => in_array(($_GET['environment'] ?? ''), ['production', 'sandbox'], true) ? $_GET['environment'] : '',
            'date_from' => \parse_jalali_date($_GET['date_from'] ?? '') ?: '',
            'date_to' => \parse_jalali_date($_GET['date_to'] ?? '') ?: '',
            'date_from_input' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to_input' => trim((string) ($_GET['date_to'] ?? '')),
            'amount_min' => max(0, (int) \normalize_money($_GET['amount_min'] ?? 0)),
            'amount_max' => max(0, (int) \normalize_money($_GET['amount_max'] ?? 0)),
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'per_page' => 25,
        ];
        $this->render('plugin:proma-zarinpal/transactions/index', [
            'title' => 'تراکنش‌های زرین‌پال',
            'filters' => $filters,
            'pagination' => (new ZarinpalTransactionRepository())->paginate($filters),
        ], 'app');
    }

    public function retry($transactionId)
    {
        $this->onlyPost();
        $repository = new ZarinpalTransactionRepository();
        $transaction = $repository->find((int) $transactionId);
        $expected = $transaction ? substr((string) $transaction['local_order_id'], -6) : '';
        if (!$transaction || $expected === '' || !hash_equals($expected, strtoupper(trim((string) ($_POST['confirmation'] ?? ''))))) {
            \set_flash('error', 'کد تأیید بررسی مجدد صحیح نیست.');
            \redirect('plugin/zarinpal/transactions');
        }
        try {
            $result = (new ZarinpalReconciliationService($repository))->retry((int) $transactionId);
            \set_flash(!empty($result['ok']) ? 'success' : 'error', $result['message'] ?? 'بررسی مجدد انجام شد.');
        } catch (\Throwable $e) {
            \set_flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'بررسی مجدد تراکنش انجام نشد.');
        }
        \redirect('plugin/zarinpal/transactions');
    }

    public function review($transactionId)
    {
        $this->onlyPost();
        $repository = new ZarinpalTransactionRepository();
        $transaction = $repository->find((int) $transactionId);
        $expected = $transaction ? substr((string) $transaction['local_order_id'], -6) : '';
        if (!$transaction || $expected === '' || !hash_equals($expected, strtoupper(trim((string) ($_POST['confirmation'] ?? ''))))) {
            \set_flash('error', 'کد تأیید بررسی دستی صحیح نیست.');
            \redirect('plugin/zarinpal/transactions');
        }
        if (in_array(($transaction['status'] ?? ''), ['paid', 'sandbox_verified', 'cancelled_by_customer', 'request_failed'], true)) {
            \set_flash('error', 'این تراکنش نهایی شده و قابل علامت‌گذاری نیست.');
            \redirect('plugin/zarinpal/transactions');
        }
        $repository->update((int) $transaction['id'], ['status' => 'manual_review']);
        (new ZarinpalLogRepository())->record('payment.manual_review', 'تراکنش برای بررسی دستی علامت‌گذاری شد.', (int) $transaction['id'], ['actor_user_id' => \Auth::id()], 'warning');
        \AuditLog::record('payment_gateway', 'zarinpal_manual_review', 'zarinpal_transaction', (int) $transaction['id'], [
            'actor_user_id' => \Auth::id(),
            'customer_id' => (int) $transaction['customer_id'],
            'contract_id' => (int) $transaction['contract_id'],
            'new_values' => ['status' => 'manual_review'],
        ]);
        \set_flash('success', 'تراکنش برای بررسی دستی علامت‌گذاری شد؛ هیچ اثر مالی ایجاد نشد.');
        \redirect('plugin/zarinpal/transactions');
    }

    public function export()
    {
        $rows = (new ZarinpalTransactionRepository())->exportRows();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="proma-zarinpal-transactions-' . date('Ymd-His') . '.csv"');
        $stream = fopen('php://output', 'wb');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['شناسه محلی', 'مشتری', 'قرارداد', 'مبلغ تومان', 'واحد', 'محیط', 'وضعیت', 'Authority', 'Reference ID', 'کارمزد', 'ساخت', 'تأیید']);
        foreach ($rows as $row) {
            fputcsv($stream, array_map([$this, 'csvCell'], array_values($row)));
        }
        fclose($stream);
        exit;
    }

    private function csvCell($value)
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/u', $value) ? "'" . $value : $value;
    }
}
