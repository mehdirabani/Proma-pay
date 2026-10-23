<?php

class MerchantPortalController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $userId = (int) Auth::id();
        $merchants = MerchantDashboardRepository::membershipsForUser($userId);
        if (!$merchants) {
            ErrorHandler::abort(403);
        }

        $merchantId = (int) ($_GET['merchant_id'] ?? $merchants[0]['merchant_id']);
        try {
            $dashboard = MerchantDashboardRepository::dashboardForUser($userId, $merchantId);
        } catch (DomainException $error) {
            ErrorHandler::abort(404);
        }

        $this->render('merchant-portal/index', [
            'title' => 'گزارش‌های فروشنده',
            'merchants' => $merchants,
            'dashboard' => $dashboard,
        ]);
    }
}
