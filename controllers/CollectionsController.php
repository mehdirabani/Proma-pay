<?php

class CollectionsController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        $operatorId = Auth::role() === 'operator' ? (int) Auth::id() : null;
        $criteria = OverdueFilterCriteria::fromRequest($_GET);
        $this->render('collections/index', [
            'title' => 'مرکز عملیات وصول',
            'center' => CollectionCommandCenter::overview($operatorId, $criteria),
            'criteria' => $criteria,
            'role' => Auth::role(),
        ]);
    }

    public function timeline($contractId)
    {
        $this->requireRole(['admin', 'operator']);
        $contractId = (int) $contractId;
        $operatorId = Auth::role() === 'operator' ? (int) Auth::id() : null;
        $contract = Contract::find($contractId);
        if (!$contract || ($operatorId && (int) ($contract['assigned_operator_id'] ?? 0) !== $operatorId)) ErrorHandler::abort(404);
        $this->render('collections/timeline', [
            'title' => 'خط زمانی وصول',
            'timeline' => CollectionCommandCenter::timeline($contractId, max(1, (int) ($_GET['page'] ?? 1))),
        ]);
    }

    public function runAutomation()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $created = CollectionCommandCenter::runStageAutomation((int) Auth::id());
            set_flash('success', to_persian_digits($created) . ' وظیفهٔ جدید وصول ساخته شد؛ اعلان‌های تکراری دوباره ارسال نشدند.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('collections');
    }

    public function completeTask($taskId)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        try {
            CollectionCommandCenter::completeTask((int) $taskId, (int) Auth::id(), Auth::role() === 'admin', [
                'result' => (string) ($_POST['completion_result'] ?? ''),
                'note' => (string) ($_POST['completion_note'] ?? ''),
                'next_action_type' => (string) ($_POST['next_action_type'] ?? ''),
                'next_followup_date' => (string) ($_POST['next_followup_date'] ?? ''),
            ]);
            set_flash('success', 'وظیفهٔ وصول تکمیل شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('collections');
    }

    public function assignContract($contractId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            CollectionCommandCenter::assignContract((int) $contractId, (int) ($_POST['operator_id'] ?? 0), (int) Auth::id());
            set_flash('success', 'مسئول پرونده تغییر کرد و وظایف باز همگام شدند.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'واگذاری پرونده انجام نشد.');
        }
        redirect('collections');
    }

    public function followup()
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        try {
            CollectionCommandCenter::recordFollowup($_POST, (int) Auth::id(), (string) Auth::role());
            set_flash('success', 'پیگیری در سابقهٔ واحد ثبت و اقدام بعدی زمان‌بندی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت پیگیری انجام نشد.');
        }
        redirect('collections');
    }

    public function promiseAction($promiseCallId)
    {
        $this->requireRole(['admin', 'operator']);
        $this->onlyPost();
        try {
            CollectionCommandCenter::updatePromise((int) $promiseCallId, (int) Auth::id(), (string) Auth::role(), (string) ($_POST['action'] ?? ''), (string) ($_POST['promise_payment_date'] ?? ''), $_POST['promise_amount_toman'] ?? null, (string) ($_POST['reason'] ?? ''));
            set_flash('success', 'تغییر قول پرداخت در تاریخچه ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تغییر قول پرداخت انجام نشد.');
        }
        redirect('collections/timeline/' . (int) ($_POST['contract_id'] ?? 0));
    }

    public function performance()
    {
        $this->requireRole('admin');
        Auth::releaseSessionLock();
        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $report = CollectionPerformanceService::report($from, $to, (int) ($_GET['operator_id'] ?? 0), max(1, min(30, (int) ($_GET['window_days'] ?? 7))), max(1, (int) ($_GET['evidence_page'] ?? 1)), (string) ($_GET['evidence_type'] ?? 'collection'));
        $this->render('collections/performance', [
            'title' => 'گزارش عملکرد وصول', 'report' => $report,
            'operators' => User::all('operator', null, 'active'), 'filters' => ['from' => $from, 'to' => $to, 'operator_id' => (int) ($_GET['operator_id'] ?? 0), 'window_days' => max(1, min(30, (int) ($_GET['window_days'] ?? 7))), 'evidence_type' => (string) ($_GET['evidence_type'] ?? 'collection')],
        ]);
    }
}
