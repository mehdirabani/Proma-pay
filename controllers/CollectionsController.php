<?php

class CollectionsController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator']);
        $operatorId = Auth::role() === 'operator' ? (int) Auth::id() : null;
        $this->render('collections/index', [
            'title' => 'مرکز عملیات وصول',
            'center' => CollectionCommandCenter::overview($operatorId),
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
            'timeline' => CollectionCommandCenter::timeline($contractId),
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
            CollectionCommandCenter::completeTask((int) $taskId, (int) Auth::id(), Auth::role() === 'admin');
            set_flash('success', 'وظیفهٔ وصول تکمیل شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('collections');
    }
}
