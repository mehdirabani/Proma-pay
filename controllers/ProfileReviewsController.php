<?php

class ProfileReviewsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $status = trim((string) ($_GET['status'] ?? 'pending'));
        if (!in_array($status, ['pending', 'approved', 'partial', 'rejected', ''], true)) {
            $status = 'pending';
        }
        $role = trim((string) ($_GET['role'] ?? ''));
        $roleOptions = PluginManager::availableRoles();
        foreach (Model::fetchAll('SELECT DISTINCT role FROM users WHERE role IS NOT NULL') as $assigned) {
            $assignedRole = (string) ($assigned['role'] ?? '');
            if ($assignedRole !== '' && !isset($roleOptions[$assignedRole])) {
                $roleOptions[$assignedRole] = role_label($assignedRole);
            }
        }
        if ($role !== '' && !isset($roleOptions[$role])) {
            $role = '';
        }
        $filters = [
            'status' => $status,
            'role' => $role,
            'q' => trim((string) ($_GET['q'] ?? '')),
            'user_id' => max(0, (int) ($_GET['user_id'] ?? 0)),
        ];
        $result = ProfileRequest::paginated($filters, $_GET['page'] ?? 1, 18);
        $this->render('profile-reviews/index', [
            'title' => 'تأیید اصلاح مشخصات',
            'requests' => $result['items'],
            'pagination' => $result,
            'summary' => ProfileRequest::statusSummary(),
            'filters' => $filters,
            'roleOptions' => $roleOptions,
        ]);
    }
}
