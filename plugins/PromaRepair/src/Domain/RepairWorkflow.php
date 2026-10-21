<?php

namespace Proma\Plugins\Repair\Domain;

final class RepairWorkflow
{
    public const STATUSES = [
        'draft', 'received', 'waiting_inspection', 'diagnosing', 'waiting_customer_approval', 'approved',
        'repairing', 'waiting_parts', 'repair_completed', 'quality_control', 'ready_for_delivery',
        'delivered', 'cancelled', 'unable_to_repair',
    ];

    private const TRANSITIONS = [
        'draft' => ['received', 'cancelled'],
        'received' => ['waiting_inspection', 'diagnosing', 'cancelled'],
        'waiting_inspection' => ['diagnosing', 'cancelled', 'unable_to_repair'],
        'diagnosing' => ['waiting_customer_approval', 'repairing', 'unable_to_repair', 'cancelled'],
        'waiting_customer_approval' => ['approved', 'cancelled', 'unable_to_repair'],
        'approved' => ['repairing', 'waiting_parts', 'cancelled'],
        'repairing' => ['waiting_parts', 'repair_completed', 'unable_to_repair', 'cancelled'],
        'waiting_parts' => ['repairing', 'cancelled', 'unable_to_repair'],
        'repair_completed' => ['quality_control', 'repairing'],
        'quality_control' => ['repairing', 'ready_for_delivery'],
        'ready_for_delivery' => ['delivered', 'repairing'],
        'delivered' => [],
        'cancelled' => [],
        'unable_to_repair' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function label(string $status): string
    {
        return [
            'draft' => 'پیش‌نویس',
            'received' => 'دستگاه تحویل شد',
            'waiting_inspection' => 'در انتظار بررسی',
            'diagnosing' => 'در حال بررسی دستگاه',
            'waiting_customer_approval' => 'در انتظار تأیید هزینه',
            'approved' => 'هزینه تأیید شد',
            'repairing' => 'در حال تعمیر',
            'waiting_parts' => 'در انتظار تأمین قطعه',
            'repair_completed' => 'تعمیر انجام شد',
            'quality_control' => 'در حال بررسی نهایی',
            'ready_for_delivery' => 'آماده تحویل',
            'delivered' => 'تحویل داده شد',
            'cancelled' => 'لغوشده',
            'unable_to_repair' => 'غیرقابل تعمیر',
        ][$status] ?? 'وضعیت نامشخص';
    }

    public static function customerLabel(string $status): string
    {
        return self::label($status);
    }
}
