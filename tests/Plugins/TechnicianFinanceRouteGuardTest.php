<?php

declare(strict_types=1);

final class TechnicianGuardHttpException extends RuntimeException
{
    public $status;

    public function __construct(int $status, string $message)
    {
        $this->status = $status;
        parent::__construct($message);
    }
}

final class Auth
{
    public static $currentRole = 'repair_technician';
    public static $currentId = 77;

    public static function role()
    {
        return self::$currentRole;
    }

    public static function id()
    {
        return self::$currentId;
    }

    public static function logout()
    {
    }
}

final class Model
{
    public static function fetch(string $sql, array $parameters = [])
    {
        return ['id' => $parameters[0] ?? 0];
    }
}

final class ErrorHandler
{
    public static function respond(int $status, string $message = '')
    {
        throw new TechnicianGuardHttpException($status, $message);
    }

    public static function log(string $context, Throwable $error, int $status)
    {
    }
}

require_once dirname(__DIR__, 2) . '/plugins/PromaRepair/src/Support/TechnicianSession.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['route'] = 'plugin/accounting/my-finance';
\Proma\Plugins\Repair\Support\TechnicianSession::guardRequest();

$_GET['route'] = 'plugin/accounting/analytics/users';
try {
    \Proma\Plugins\Repair\Support\TechnicianSession::guardRequest();
    $assert(false, 'Technician access to cross-user analytics must remain blocked.');
} catch (TechnicianGuardHttpException $error) {
    $assert($error->status === 403, 'Cross-user analytics must be rejected with HTTP 403.');
}

echo "TECHNICIAN_SELF_FINANCE_ROUTE_GUARD_OK\n";
