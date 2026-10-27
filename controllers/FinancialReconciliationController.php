<?php

final class FinancialReconciliationController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        Auth::releaseSessionLock();
        $filters = $this->filters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $list = FinancialReconciliationCenter::listDiscrepancies($filters, $page, 30);
        $runId = max(0, (int) ($_GET['run_id'] ?? 0));
        $run = $runId ? FinancialReconciliationCenter::run($runId) : [];
        $this->render('financial-reconciliation/index', [
            'title' => 'مرکز تطبیق مالی',
            'summary' => FinancialReconciliationCenter::summary(),
            'runs' => FinancialReconciliationCenter::latestRuns(),
            'run' => $run,
            'filters' => $filters,
            'list' => $list,
            'canManage' => Auth::role() === 'admin',
            'role' => Auth::role(),
        ]);
    }

    public function show($id)
    {
        $this->requireRole(['admin', 'operator']);
        Auth::releaseSessionLock();
        $detail = FinancialReconciliationCenter::discrepancy((int) $id);
        $staff = Auth::role() === 'admin'
            ? Model::fetchAll("SELECT id,full_name,role FROM users WHERE role IN ('admin','operator') AND archived_at IS NULL ORDER BY role,full_name LIMIT 200")
            : [];
        $this->render('financial-reconciliation/show', [
            'title' => 'پرونده مغایرت #' . (int) $id,
            'detail' => $detail,
            'users' => $staff,
            'canManage' => Auth::role() === 'admin',
            'role' => Auth::role(),
        ]);
    }

    public function start()
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        Auth::releaseSessionLock();
        try {
            $contractId = (int) ($_POST['contract_id'] ?? 0);
            $run = FinancialReconciliationCenter::startRun((int) Auth::id(), $contractId > 0 ? $contractId : null, (int) ($_POST['batch_size'] ?? 20));
            set_flash('success', 'اجرای اسکن ثبت شد. ' . (int) ($run['checked_count'] ?? 0) . ' قرارداد بررسی شد؛ وضعیت: ' . $this->runLabel((string) ($run['status'] ?? 'running')) . '.');
            redirect('financial-reconciliation', ['run_id' => (int) $run['id']]);
        } catch (Throwable $e) {
            set_flash('error', $this->message($e, 'اجرای اسکن آغاز نشد.'));
            redirect('financial-reconciliation');
        }
    }

    public function continueRun($id)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        Auth::releaseSessionLock();
        try {
            $run = FinancialReconciliationCenter::continueRun((int) $id, (int) Auth::id(), (int) ($_POST['batch_size'] ?? 20));
            set_flash('success', 'پیشرفت اسکن: ' . (int) ($run['checked_count'] ?? 0) . ' از ' . (int) ($run['target_count'] ?? 0) . ' قرارداد؛ وضعیت: ' . $this->runLabel((string) ($run['status'] ?? 'running')) . '.');
        } catch (Throwable $e) {
            set_flash('error', $this->message($e, 'ادامه اسکن انجام نشد.'));
        }
        redirect('financial-reconciliation', ['run_id' => (int) $id]);
    }

    public function export()
    {
        $this->requireRole(['admin', 'operator']);
        Auth::releaseSessionLock();
        $csv = FinancialReconciliationCenter::exportCsv($this->filters());
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="proma-financial-reconciliation-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $csv;
        exit;
    }

    public function review($id)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        $this->runCaseAction((int) $id, static function () use ($id) { FinancialReconciliationCenter::startReview((int) $id, (int) Auth::id(), (string) ($_POST['reason'] ?? '')); }, 'پرونده وارد مرحله بررسی شد.');
    }

    public function note($id)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        $this->runCaseAction((int) $id, static function () use ($id) { FinancialReconciliationCenter::addNote((int) $id, (int) Auth::id(), (string) ($_POST['note'] ?? '')); }, 'یادداشت به تاریخچه پرونده افزوده شد.');
    }

    public function assign($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $this->runCaseAction((int) $id, static function () use ($id) { FinancialReconciliationCenter::assign((int) $id, (int) ($_POST['assigned_user_id'] ?? 0), (int) Auth::id()); }, 'پرونده ارجاع شد.');
    }

    public function propose($id)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        Auth::releaseSessionLock();
        try {
            $result = FinancialReconciliationCenter::proposeInstallmentRefresh((int) $id, (int) Auth::id(), (string) ($_POST['reason'] ?? ''));
            set_flash($result['status'] === 'stale' ? 'error' : 'success', $result['status'] === 'stale' ? 'منابع مالی پس از اسکن تغییر کرده‌اند؛ پیشنهاد ثبت نشد و اسکن تازه لازم است.' : 'پیشنهاد بازسازی وضعیت قسط برای تأیید مدیر ثبت شد.');
        } catch (Throwable $e) { set_flash('error', $this->message($e, 'ثبت پیشنهاد اصلاح انجام نشد.')); }
        redirect('financial-reconciliation/show/' . (int) $id);
    }

    public function decide($uuid)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        Auth::releaseSessionLock();
        try {
            $approve = ($_POST['decision'] ?? '') === 'approve';
            $result = FinancialReconciliationCenter::decideProposal((string) $uuid, (int) Auth::id(), $approve, (string) ($_POST['reason'] ?? ''));
            if ($result['status'] === 'stale') set_flash('error', 'منبع مالی تغییر کرده است؛ پیشنهاد منقضی شد و اصلاحی انجام نشد.');
            else set_flash('success', $approve ? 'پیشنهاد پس از بررسی مستقل تأیید شد.' : 'پیشنهاد اصلاح رد شد.');
        } catch (Throwable $e) { set_flash('error', $this->message($e, 'تصمیم‌گیری درباره پیشنهاد انجام نشد.')); }
        redirect('financial-reconciliation');
    }

    public function applyProposal($uuid)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        Auth::releaseSessionLock();
        try {
            $result = FinancialReconciliationCenter::applyProposal((string) $uuid, (int) Auth::id());
            if (($result['status'] ?? '') === 'stale') set_flash('error', 'منبع مالی پس از تأیید تغییر کرده است؛ هیچ اصلاحی اعمال نشد.');
            else set_flash('success', !empty($result['idempotent_replay']) ? 'این پیشنهاد قبلاً اجرا شده است؛ تغییر مالی تکرار نشد.' : 'بازسازی وضعیت قسط انجام و در همان تراکنش راستی‌آزمایی شد.');
        } catch (Throwable $e) { set_flash('error', $this->message($e, 'اجرای پیشنهاد انجام نشد؛ وضعیت مالی بدون تأیید حسابرسی تغییر نمی‌کند.')); }
        redirect('financial-reconciliation');
    }

    public function verify($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $this->runCaseAction((int) $id, static function () use ($id) {
            $ok = FinancialReconciliationCenter::verifyAndResolve((int) $id, (int) Auth::id(), (string) ($_POST['reason'] ?? ''));
            if (!$ok) throw new InvalidArgumentException('مغایرت در تطبیق مجدد باقی است؛ پرونده بسته نشد.', 409);
        }, 'تطبیق مجدد موفق بود و پرونده بسته شد.');
    }

    public function acceptException($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $this->runCaseAction((int) $id, static function () use ($id) { FinancialReconciliationCenter::acceptException((int) $id, (int) Auth::id(), (string) ($_POST['reason'] ?? '')); }, 'پرونده با علت ثبت‌شده به‌عنوان استثنا پذیرفته شد.');
    }

    private function runCaseAction(int $id, callable $action, string $success): void
    {
        Auth::releaseSessionLock();
        try { $action(); set_flash('success', $success); }
        catch (Throwable $e) { set_flash('error', $this->message($e, 'اقدام روی پرونده انجام نشد.')); }
        redirect('financial-reconciliation/show/' . $id);
    }

    private function filters(): array
    {
        $keys = ['contract', 'customer', 'date_from', 'date_to', 'rule_id', 'severity', 'status', 'classification', 'assigned_user_id', 'payment_id', 'ledger_event_id'];
        $filters = [];
        foreach ($keys as $key) $filters[$key] = trim((string) ($_GET[$key] ?? ''));
        return $filters;
    }

    private function message(Throwable $e, string $fallback): string
    {
        if ($e instanceof InvalidArgumentException && trim($e->getMessage()) !== '') return $e->getMessage();
        if ($e instanceof RuntimeException && $e->getCode() === 409) return $e->getMessage();
        ErrorHandler::log('financial_reconciliation', $e, 500);
        return $fallback . ' شناسه درخواست: ' . ErrorHandler::requestId();
    }

    private function runLabel(string $status): string
    {
        return ['running' => 'در حال اجرا', 'completed' => 'کامل', 'partial' => 'ناقص', 'failed' => 'ناموفق'][$status] ?? 'نامشخص';
    }
}
