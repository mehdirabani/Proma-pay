<?php

class ReviewController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $tab = preg_replace('/[^a-z0-9_-]/i', '', $_GET['tab'] ?? 'identity') ?: 'identity';
        $this->render('review/index', [
            'title' => 'بررسی موارد ارسالی',
            'activeTab' => in_array($tab, ['identity', 'receipts'], true) ? $tab : 'identity',
            'identityRequests' => IdentityDocument::pending(),
            'receiptRequests' => PaymentReceipt::pending(),
            'identityPendingCount' => IdentityDocument::pendingCount(),
            'receiptPendingCount' => PaymentReceipt::pendingCount(),
        ]);
    }
}
