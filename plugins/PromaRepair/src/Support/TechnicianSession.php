<?php

namespace Proma\Plugins\Repair\Support;

final class TechnicianSession
{
    public static function canLogin(array $user): bool
    {
        return self::isActiveTechnician((int) ($user['id'] ?? 0));
    }

    public static function isActiveTechnician(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        return (bool) \Model::fetch(
            "SELECT u.id FROM users u
             JOIN proma_repair_technicians t ON t.user_id = u.id
             WHERE u.id = ? AND u.role = 'repair_technician' AND u.status = 'active'
               AND t.active = 1 AND t.role_active = 1 LIMIT 1",
            [$userId]
        );
    }

    public static function guardRequest(): void
    {
        if ((string) \Auth::role() !== 'repair_technician') {
            return;
        }
        try {
            $active = self::isActiveTechnician((int) \Auth::id());
        } catch (\Throwable $e) {
            \ErrorHandler::log('repair_session', $e, 503);
            \ErrorHandler::respond(503, 'پنل تعمیرکار موقتاً در دسترس نیست.');
        }
        if (!$active) {
            \Auth::logout();
            \ErrorHandler::respond(403, 'حساب تعمیرکار غیرفعال است.');
        }
        $route = trim((string) ($_GET['route'] ?? ''), '/');
        if (in_array($route, ['', 'dashboard', 'dashboard/index'], true)) {
            \redirect('plugin/repair/dashboard');
        }
        if (stripos($route, 'plugin/repair/') === 0
            || in_array($route, ['auth/login', 'auth/logout'], true)) {
            return;
        }
        // The repair dashboard exposes this self-only financial summary link.
        // AccountingController::myFinance() binds the query to Auth::id().
        if ($route === 'plugin/accounting/my-finance') {
            return;
        }
        \ErrorHandler::respond(403, 'این بخش در دسترس حساب تعمیرکار نیست.');
    }
}
