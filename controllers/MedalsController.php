<?php

class MedalsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $filter = in_array($_GET['filter'] ?? '', ['automatic', 'manual', 'active', 'inactive', 'reversible', 'permanent'], true) ? $_GET['filter'] : '';
        $this->render('medals/index', [
            'title' => 'مدیریت مدال‌ها',
            'filter' => $filter,
            'definitions' => Medal::definitionsWithStats($filter),
            'summary' => Medal::managementSummary(),
            'history' => Medal::history(),
        ]);
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $slug = preg_replace('/[^a-z0-9_-]/i', '-', trim((string) ($_POST['slug'] ?? '')));
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($slug === '' || $title === '') {
            set_flash('error', 'شناسه و عنوان مدال الزامی است.');
            redirect('medals');
        }
        try {
            $minimum = trim((string) to_english_digits($_POST['criteria_minimum'] ?? ''));
            $maximum = trim((string) to_english_digits($_POST['criteria_maximum'] ?? ''));
            $criteria = [];
            if ($minimum !== '') {
                $criteria['minimum'] = max(0, (int) $minimum);
            }
            if ($maximum !== '') {
                $criteria['maximum'] = max(0, (int) $maximum);
            }
            $awardType = in_array($_POST['award_type'] ?? '', ['automatic', 'manual'], true) ? $_POST['award_type'] : 'automatic';
            $behaviorType = in_array($_POST['behavior_type'] ?? '', ['permanent', 'reversible', 'manual'], true)
                ? $_POST['behavior_type']
                : ($awardType === 'manual' ? 'manual' : 'permanent');
            $revocationBehavior = $behaviorType === 'reversible' && ($_POST['revocation_behavior'] ?? '') === 'automatic' ? 'automatic' : 'never';
            $reactivationBehavior = ($_POST['reactivation_behavior'] ?? '') === 'new' ? 'new' : 'restore';
            Model::execute('INSERT INTO medal_definitions (slug, title, short_description, full_description, how_to_earn, icon_key, color, category, points, award_type, behavior_type, revocation_behavior, reactivation_behavior, criteria_type, criteria_json, is_repeatable, is_active, sort_order, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE title = VALUES(title), short_description = VALUES(short_description), full_description = VALUES(full_description), how_to_earn = VALUES(how_to_earn), icon_key = VALUES(icon_key), color = VALUES(color), category = VALUES(category), points = VALUES(points), award_type = VALUES(award_type), behavior_type = VALUES(behavior_type), revocation_behavior = VALUES(revocation_behavior), reactivation_behavior = VALUES(reactivation_behavior), criteria_type = VALUES(criteria_type), criteria_json = VALUES(criteria_json), is_repeatable = VALUES(is_repeatable), is_active = VALUES(is_active), sort_order = VALUES(sort_order), updated_at = NOW()', [$slug, $title, trim((string) ($_POST['short_description'] ?? '')), trim((string) ($_POST['full_description'] ?? '')), trim((string) ($_POST['how_to_earn'] ?? '')), preg_replace('/[^a-z0-9_-]/i', '', (string) ($_POST['icon_key'] ?? 'award')) ?: 'award', sanitize_hex_color($_POST['color'] ?? '', '#f59e0b'), trim((string) ($_POST['category'] ?? 'activity')), (int) to_english_digits($_POST['points'] ?? 0), $awardType, $behaviorType, $revocationBehavior, $reactivationBehavior, trim((string) ($_POST['criteria_type'] ?? '')), json_encode($criteria, JSON_UNESCAPED_UNICODE), isset($_POST['is_repeatable']) ? 1 : 0, isset($_POST['is_active']) ? 1 : 0, (int) to_english_digits($_POST['sort_order'] ?? 0), Auth::id()]);
            set_flash('success', 'تعریف مدال ذخیره شد.');
        } catch (Throwable $e) {
            set_flash('error', 'ذخیره تعریف مدال انجام نشد.');
        }
        redirect('medals');
    }

    public function revoke($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            Medal::revoke((int) $id, Auth::id(), $_POST['reason'] ?? 'لغو دستی مدال');
            set_flash('success', 'مدال لغو شد و تاریخچه آن حفظ شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'لغو مدال انجام نشد.');
        }
        redirect('medals');
    }

    public function award($userId)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            Medal::award((int) $userId, $_POST['slug'] ?? '', 'manual', Auth::id(), $_POST['note'] ?? '', $_POST['contract_id'] ?? null, $_POST['payment_id'] ?? null);
            set_flash('success', 'مدال به کاربر اعطا شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'اعطای مدال انجام نشد.');
        }
        redirect('customers/show/' . (int) $userId);
    }

    public function restore($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            Medal::restore((int) $id, Auth::id(), $_POST['reason'] ?? 'بازگردانی مدال');
            set_flash('success', 'مدال بازگردانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'بازگردانی مدال انجام نشد.');
        }
        redirect('medals');
    }

    public function toggle($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $active = Medal::toggleDefinition((int) $id, Auth::id());
            set_flash('success', $active ? 'مدال فعال شد.' : 'مدال غیرفعال شد؛ سوابق قبلی حفظ شده‌اند.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تغییر وضعیت مدال انجام نشد.');
        }
        redirect('medals');
    }

    public function synchronize()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $processed = Medal::synchronizeCustomers(Auth::id(), $_POST['customer_id'] ?? null, 100);
            set_flash('success', 'همگام‌سازی مدال برای ' . to_persian_digits($processed) . ' مشتری انجام شد.');
        } catch (Throwable $e) {
            ErrorHandler::log('medal_synchronize', $e, 500);
            set_flash('error', 'همگام‌سازی مدال انجام نشد.');
        }
        redirect('medals');
    }
}
