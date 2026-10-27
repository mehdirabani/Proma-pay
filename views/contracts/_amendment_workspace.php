<?php
$workspace = $amendmentWorkspace ?? [];
$versions = $workspace['versions'] ?? [];
$amendments = $workspace['amendments'] ?? [];
$scheduleEditor = $workspace['schedule_editor'] ?? [];
$currentFinancialVersion = $workspace['current_version'] ?? null;
$amendmentMoney = static function ($value) { return number_format(normalize_money($value ?? 0)); };
$amendmentIdempotencyKey = bin2hex(random_bytes(24));
$amendmentStatusLabels = [
    'pending_approval' => 'در انتظار تأیید', 'approved' => 'تأیید داخلی؛ منتظر اعمال', 'rejected' => 'ردشده',
    'applied' => 'اعمال‌شده', 'conflict' => 'تعارض نسخه پایه', 'cancelled' => 'لغوشده',
];
?>
<section class="card" id="contract-amendments" data-contract-tab-panel="amendments" hidden>
  <div class="card-header"><div><h2>اصلاحیه‌ها و نسخه مالی قرارداد</h2><p>تغییر نرخ، مبلغ، مدت و برنامه اقساط از این مسیر ثبت می‌شود. درخواست، تأیید داخلی و اعمال، مراحل جدا هستند.</p></div></div>
  <div class="card-body">
    <?php if (!empty($workspace['migration_required'])): ?>
      <div class="notice warning">زیرساخت نسخه‌بندی هنوز در پایگاه داده نصب نشده است. Migration افزایشی v7.0.4 را پیش از ثبت اصلاحیه اجرا کنید.</div>
    <?php else: ?>
      <?php if ($currentFinancialVersion): ?>
        <?php $currentProvenance = (string) ($currentFinancialVersion['provenance'] ?? ''); ?>
        <div class="proma-preview-grid">
          <span><small>نسخه فعال مالی</small><strong>V<?= to_persian_digits($currentFinancialVersion['version_number'] ?? 1) ?></strong></span>
          <span><small>مبنای نسخه</small><strong><?= str_starts_with($currentProvenance, 'legacy_baseline') ? 'Baseline قدیمی؛ تاریخچه اولیه ناقص' : (str_starts_with($currentProvenance, 'approved_internal') ? 'اصلاحیه با تأیید داخلی' : 'وضعیت ثبت‌شده در زمان ایجاد') ?></strong></span>
          <span><small>اثر از</small><strong><?= e(jdate($currentFinancialVersion['effective_from'] ?? '')) ?></strong></span>
          <span><small>اعتبار Checksum</small><strong><?= !empty($currentFinancialVersion['hash_valid']) ? 'معتبر' : 'نامعتبر؛ بررسی لازم است' ?></strong></span>
        </div>
        <?php if (str_starts_with($currentProvenance, 'legacy_baseline')): ?><div class="notice warning mt-3">این نسخه فقط وضعیت مشاهده‌شده پس از نصب را ثبت می‌کند و نسخه اولیه یا تأیید تاریخی قرارداد را اثبات نمی‌کند.</div><?php endif; ?>
        <?php if (str_starts_with($currentProvenance, 'approved_internal')): ?><div class="notice warning mt-3">تأیید داخلی سامانه به‌تنهایی رضایت یا امضای طرف قرارداد را اثبات نمی‌کند؛ وضعیت سند حقوقی جداگانه بررسی شود.</div><?php endif; ?>
      <?php endif; ?>

      <?php if ($canManageAmendments): ?>
        <details class="mt-3">
          <summary class="btn secondary">ثبت درخواست اصلاحیه مالی</summary>
          <div class="card mt-3">
            <div class="card-body">
              <div class="notice warning">پیش‌نمایش فقط برنامه آینده را با ارقام صریح واردشده مقایسه می‌کند. موتور مالی نسخهٔ فعلی سیاستی برای محاسبه مجدد مانده آینده ندارد؛ مبلغ قسط جدید خودکار حدس زده نمی‌شود. اقساط دارای سابقه مالی باید بدون تغییر در برنامه بمانند.</div>
              <form method="post" action="<?= e(url('contracts/requestAmendment/' . (int) $contract['id'])) ?>" data-contract-amendment-form data-preview-url="<?= e(url('contracts/previewAmendment/' . (int) $contract['id'])) ?>" data-next-row="<?= count($scheduleEditor) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="preview_fingerprint" value="" data-amendment-preview-fingerprint>
                <input type="hidden" name="idempotency_key" value="<?= e($amendmentIdempotencyKey) ?>">
                <div class="form-grid two mt-3">
                  <label class="required-field">دلیل اصلاحیه<textarea name="reason" rows="3" required minlength="5" maxlength="4000"></textarea></label>
                  <label class="required-field">تاریخ اثرگذاری<input type="date" name="effective_on" value="<?= e(date('Y-m-d')) ?>" required></label>
                  <label>اصل قرارداد<input type="number" name="terms[principal_amount]" min="1" step="1" value="<?= e((string) normalize_money($contract['principal_amount'] ?? 0)) ?>" required></label>
                  <label>پیش‌پرداخت<input type="number" name="terms[down_payment_amount]" min="0" step="1" value="<?= e((string) normalize_money($contract['down_payment_amount'] ?? 0)) ?>" required></label>
                  <label>نرخ سود ماهانه<input type="number" name="terms[monthly_interest_rate]" min="0" step="0.0001" value="<?= e((string) ($contract['monthly_interest_rate'] ?? 0)) ?>" required></label>
                  <label>روش سود<select name="terms[interest_type]"><option value="simple" <?= ($contract['interest_type'] ?? '') === 'simple' ? 'selected' : '' ?>>ساده</option><option value="compound" <?= ($contract['interest_type'] ?? '') === 'compound' ? 'selected' : '' ?>>مرکب</option></select></label>
                  <label>تعداد کل اقساط<input type="number" name="terms[months]" min="1" max="240" step="1" value="<?= (int) ($contract['months'] ?? 1) ?>" required></label>
                  <label>تاریخ شروع قرارداد<input type="date" name="terms[start_date]" value="<?= e((string) ($contract['start_date'] ?? '')) ?>" required></label>
                  <label>اولین سررسید<input type="date" name="terms[first_due_date]" value="<?= e((string) ($contract['first_due_date'] ?? '')) ?>" required></label>
                </div>
                <h3 class="mt-3">برنامه پیشنهادی از تاریخ اثرگذاری</h3>
                <p class="text-muted">ردیف‌های گذشته خارج از اصلاحیه می‌مانند. تعداد کل اقساط باید با ردیف‌های فعال تاریخی و همین برنامه برابر باشد. ردیف‌های دارای سابقه مالی قفل هستند.</p>
                <div class="table-wrap"><table><thead><tr><th>قسط موجود</th><th>تاریخ سررسید</th><th>مبلغ</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody data-amendment-schedule-rows>
                  <?php foreach ($scheduleEditor as $rowIndex => $scheduleRow): $rowLocked = !empty($scheduleRow['has_history']); ?>
                    <tr data-amendment-row data-protected="<?= $rowLocked ? '1' : '0' ?>">
                      <td><?= $scheduleRow['installment_id'] ? 'قسط #' . to_persian_digits($scheduleRow['installment_number']) : 'قسط جدید' ?><input type="hidden" name="schedule[<?= (int) $rowIndex ?>][installment_id]" value="<?= (int) ($scheduleRow['installment_id'] ?? 0) ?>"></td>
                      <td><input type="date" name="schedule[<?= (int) $rowIndex ?>][due_date]" value="<?= e((string) ($scheduleRow['due_date'] ?? '')) ?>" required <?= $rowLocked ? 'readonly' : '' ?>></td>
                      <td><input type="number" name="schedule[<?= (int) $rowIndex ?>][base_amount]" min="1" step="1" value="<?= e((string) normalize_money($scheduleRow['base_amount'] ?? 0)) ?>" required <?= $rowLocked ? 'readonly' : '' ?>></td>
                      <td><?= $rowLocked ? 'محافظت‌شده؛ حفظ عین مبلغ و تاریخ' : 'قابل اصلاح در صورت تأیید' ?></td>
                      <td><?php if (!$rowLocked): ?><button class="btn small danger" type="button" data-remove-amendment-row>حذف از برنامه آینده</button><?php else: ?>—<?php endif; ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$scheduleEditor): ?><tr data-amendment-row data-protected="0"><td>قسط جدید<input type="hidden" name="schedule[0][installment_id]" value="0"></td><td><input type="date" name="schedule[0][due_date]" required></td><td><input type="number" name="schedule[0][base_amount]" min="1" step="1" required></td><td>قابل اصلاح در صورت تأیید</td><td><button class="btn small danger" type="button" data-remove-amendment-row>حذف</button></td></tr><?php endif; ?>
                </tbody></table></div>
                <div class="actions mt-3"><button class="btn secondary" type="button" data-add-amendment-row>افزودن قسط آینده</button><button class="btn secondary" type="button" data-preview-amendment>پیش‌نمایش مالیِ فقط‌خواندنی</button><button class="btn" type="submit" data-submit-amendment disabled>ثبت درخواست برای تأیید</button></div>
                <div class="notice mt-3" data-amendment-preview-output aria-live="polite">پیش‌نمایش را اجرا کنید تا اختلاف مبلغ آینده و شناسه نسخه پایه ثبت شود.</div>
              </form>
            </div>
          </div>
        </details>
      <?php endif; ?>

      <?php if ($amendments): ?>
        <h3 class="mt-3">گردش اصلاحیه‌ها</h3>
        <?php foreach ($amendments as $amendment): $proposedChanges = json_decode((string) ($amendment['requested_changes_json'] ?? ''), true) ?: []; $impact = $amendment['impact'] ?? []; ?>
          <details class="card mt-3">
            <summary>اصلاحیه <?= to_persian_digits($amendment['amendment_number']) ?> · <?= e($amendmentStatusLabels[$amendment['status']] ?? $amendment['status']) ?> · ثبت‌کننده: <?= e($amendment['requested_by_name'] ?? ('#' . $amendment['requested_by'])) ?></summary>
            <div class="card-body">
              <p><strong>دلیل:</strong> <?= nl2br(e($amendment['reason'])) ?></p>
              <p><strong>تاریخ اثرگذاری:</strong> <?= e(jdate($amendment['effective_on'])) ?> · <strong>تأییدکننده:</strong> <?= e($amendment['approved_by_name'] ?? 'تأیید نشده') ?> · <strong>اعمال‌کننده:</strong> <?= e($amendment['applied_by_name'] ?? 'اعمال نشده') ?></p>
              <?php if (!empty($amendment['document_version_id'])): ?><div class="notice info mt-3">پیش‌نویس داخلی الحاقیه به نسخه مالی متصل است: سند V<?= to_persian_digits($amendment['document_version_number'] ?? $amendment['document_version_id']) ?>. این سند منتشر یا نهایی نشده و رضایت طرف قرارداد در سامانه ثبت نشده است. <?php if ($canManageAmendments): ?><a class="btn small secondary" href="<?= e(url('contracts/printDocumentVersion/' . (int) $amendment['document_version_id'])) ?>" target="_blank" rel="noopener">مشاهده پیش‌نویس</a><?php endif; ?></div><?php endif; ?>
              <div class="proma-preview-grid"><span><small>تعهد آینده قبل</small><strong><?= $amendmentMoney($impact['current_future_total'] ?? 0) ?></strong></span><span><small>تعهد آینده پیشنهادی</small><strong><?= $amendmentMoney($impact['proposed_future_total'] ?? 0) ?></strong></span><span><small>اختلاف</small><strong><?= $amendmentMoney($impact['future_total_delta'] ?? 0) ?></strong></span><span><small>نسخه پایه</small><strong><?= to_persian_digits($amendment['base_version_id']) ?></strong></span></div>
              <div class="notice warning mt-3">تأیید داخلی، امضا یا رضایت طرف قرارداد را ثبت نمی‌کند. مانده اصل و سود آینده به‌علت نبود سیاست محاسبه معتبر جداگانه برآورد نشده‌اند.</div>
              <?php if (!empty($proposedChanges['terms'])): ?><div class="table-wrap mt-3"><table><thead><tr><th>شرط تغییرکرده</th><th>قبل</th><th>پیشنهادی</th></tr></thead><tbody><?php foreach ($proposedChanges['terms'] as $field => $change): ?><tr><td><?= e($field) ?></td><td><?= e(is_scalar($change['before'] ?? null) ? (string) ($change['before'] ?? '—') : '—') ?></td><td><?= e(is_scalar($change['after'] ?? null) ? (string) ($change['after'] ?? '—') : '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
              <?php if ($canManageAmendments): ?>
                <div class="actions mt-3">
                  <?php if (($amendment['status'] ?? '') === 'pending_approval' && (int) $amendment['requested_by'] !== (int) Auth::id()): ?>
                    <form method="post" action="<?= e(url('contracts/approveAmendment/' . (int) $amendment['id'])) ?>"><?= csrf_field() ?><button class="btn success" type="submit">تأیید داخلی</button></form>
                  <?php endif; ?>
                  <?php if (($amendment['status'] ?? '') === 'pending_approval'): ?>
                    <form method="post" action="<?= e(url('contracts/rejectAmendment/' . (int) $amendment['id'])) ?>" class="actions"><?= csrf_field() ?><input name="rejection_reason" required minlength="3" placeholder="علت رد"><button class="btn danger" type="submit">رد</button></form>
                  <?php endif; ?>
                  <?php if (($amendment['status'] ?? '') === 'approved' && (string) $amendment['effective_on'] <= date('Y-m-d')): ?>
                    <form method="post" action="<?= e(url('contracts/applyAmendment/' . (int) $amendment['id'])) ?>" onsubmit="return confirm('شرایط و اقساط آینده طبق Snapshot تأییدشده در نسخه جدید ثبت شود؟')"><?= csrf_field() ?><button class="btn" type="submit">اعمال و ساخت نسخه جدید</button></form>
                  <?php elseif (($amendment['status'] ?? '') === 'approved'): ?><span class="badge badge-light-info">منتظر تاریخ اثرگذاری</span><?php endif; ?>
                  <?php if (in_array((string) $amendment['status'], ['pending_approval', 'approved', 'conflict'], true) && (int) $amendment['requested_by'] === (int) Auth::id()): ?>
                    <form method="post" action="<?= e(url('contracts/cancelAmendment/' . (int) $amendment['id'])) ?>"><?= csrf_field() ?><input name="cancel_reason" required minlength="3" placeholder="علت لغو"><button class="btn small secondary" type="submit">لغو درخواست</button></form>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($amendment['history'])): ?><details class="mt-3"><summary>رویدادهای حسابرسی اصلاحیه</summary><div class="table-wrap"><table><thead><tr><th>رویداد</th><th>اقدام‌کننده</th><th>وضعیت</th><th>زمان</th><th>دلیل</th></tr></thead><tbody><?php foreach ($amendment['history'] as $event): ?><tr><td><?= e($event['action']) ?></td><td><?= e($event['actor_name'] ?? 'سیستم') ?></td><td><?= e(($event['old_status'] ?? '—') . ' ← ' . ($event['new_status'] ?? '—')) ?></td><td><?= e(jdatetime($event['created_at'])) ?></td><td><?= e($event['reason'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
      <?php endif; ?>

      <h3 class="mt-3">نسخه‌های شرایط مالی</h3>
      <div class="table-wrap"><table><thead><tr><th>نسخه</th><th>منشأ</th><th>ثبت‌کننده</th><th>اثر از</th><th>کامل بودن سابقه</th><th>Checksum</th><th>Snapshot</th></tr></thead><tbody>
        <?php foreach ($versions as $version): ?>
          <tr><td>V<?= to_persian_digits($version['version_number']) ?><?= !empty($version['is_current']) ? ' · فعال' : '' ?></td><td><?= e($version['provenance']) ?></td><td><?= e($version['created_by_name'] ?? 'ثبت نشده') ?></td><td><?= e(jdate($version['effective_from'])) ?></td><td><?= !empty($version['history_complete']) ? 'از این نسخه کامل' : 'تاریخچه پیش از این نسخه ناقص' ?></td><td><code><?= e(substr((string) $version['content_hash'], 0, 16)) ?>…</code> <?= !empty($version['hash_valid']) ? '✓' : '⚠' ?></td><td><details><summary>مشاهده شرایط و اقساط</summary><pre><?= e(json_encode(['terms' => $version['terms'] ?? [], 'schedule' => $version['schedule'] ?? []], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre></details></td></tr>
        <?php endforeach; ?>
        <?php if (!$versions): ?><tr><td colspan="7" class="empty">هنوز نسخه مالی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
</section>

