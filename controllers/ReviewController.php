<?php

class ReviewController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $tab = preg_replace('/[^a-z0-9_-]/i', '', $_GET['tab'] ?? 'identity') ?: 'identity';
        if ($tab === 'profile') {
            redirect('profile-reviews', [
                'status' => $_GET['status'] ?? 'pending',
                'role' => $_GET['role'] ?? '',
                'q' => $_GET['q'] ?? '',
            ]);
        }
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
