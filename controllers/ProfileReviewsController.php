<?php

class ProfileReviewsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $filters = [
            'status' => $_GET['status'] ?? 'pending',
            'role' => $_GET['role'] ?? '',
            'q' => $_GET['q'] ?? '',
            'user_id' => $_GET['user_id'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'per_page' => 24,
        ];
        $result = ProfileRequest::paginated($filters);
        $requests = $result['items'];
        $histories = ProfileRequest::historiesForUsers(array_column($requests, 'user_id'));

        $this->render('profile-reviews/index', [
            'title' => 'تأیید اصلاح مشخصات',
            'requests' => $requests,
            'pagination' => $result,
            'summary' => ProfileRequest::summary(),
            'histories' => $histories,
            'filters' => $filters,
        ]);
    }
}
