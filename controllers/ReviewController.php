<?php

class ReviewController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $tab = preg_replace('/[^a-z0-9_-]/i', '', $_GET['tab'] ?? 'identity') ?: 'identity';
        $this->render('review/index', [
            'title' => 'بررسی موارد ارسالی',
            'activeTab' => in_array($tab, ['identity', 'receipts', 'profile'], true) ? $tab : 'identity',
            'identityRequests' => IdentityDocument::pending(),
            'receiptRequests' => PaymentReceipt::pending(),
            'identityPendingCount' => IdentityDocument::pendingCount(),
            'receiptPendingCount' => PaymentReceipt::pendingCount(),
            'profileRequests' => ProfileRequest::all([
                'status' => $_GET['status'] ?? 'pending',
                'role' => $_GET['role'] ?? '',
                'q' => $_GET['q'] ?? '',
            ]),
            'profilePendingCount' => ProfileRequest::countByStatus('pending'),
            'profileStatus' => $_GET['status'] ?? 'pending',
        ]);
    }
}
