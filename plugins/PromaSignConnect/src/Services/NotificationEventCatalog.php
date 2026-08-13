<?php
namespace Proma\Plugins\SignConnect\Services;

final class NotificationEventCatalog
{
    public const CATEGORIES = [
        'security' => 'امنیت و ورود',
        'customer' => 'مشتری و حساب کاربری',
        'contract' => 'قرارداد',
        'signature' => 'امضای الکترونیکی',
        'installment' => 'اقساط',
        'payment' => 'پرداخت',
        'legal' => 'حقوقی',
        'file' => 'فایل و اسناد',
        'general' => 'عمومی',
    ];

    public function all(string $search = ''): array
    {
        $where = 'WHERE is_active=1';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (title_fa LIKE ? OR event_key LIKE ? OR description_fa LIKE ?)';
            $term = '%' . mb_substr(trim($search), 0, 100) . '%';
            $params = [$term, $term, $term];
        }
        return \Model::fetchAll(
            "SELECT * FROM proma_connect_event_catalog {$where}
             ORDER BY category,sort_order,event_key LIMIT 250",
            $params
        );
    }

    public function find(string $eventKey): ?array
    {
        if (!preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $eventKey)) return null;
        $row = \Model::fetch(
            'SELECT * FROM proma_connect_event_catalog WHERE event_key=? AND is_active=1 LIMIT 1',
            [$eventKey]
        );
        return $row ?: null;
    }

    public function grouped(array $events): array
    {
        $groups = [];
        foreach ($events as $event) {
            $category = (string) ($event['category'] ?? 'general');
            $groups[$category][] = $event;
        }
        return $groups;
    }
}

