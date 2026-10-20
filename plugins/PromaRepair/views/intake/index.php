<?php
$parentRepair = $parentRepair ?? null;
$parentAccessories = $parentRepair ? (json_decode((string) ($parentRepair['accessories'] ?? ''), true) ?: []) : [];
$parentConditions = $parentRepair ? (json_decode((string) ($parentRepair['condition_flags'] ?? ''), true) ?: []) : [];
$deviceTypes = ['mobile'=>'موبایل','tablet'=>'تبلت','laptop'=>'لپ‌تاپ','smartwatch'=>'ساعت هوشمند','headphones'=>'هدفون','console'=>'کنسول','accessory'=>'لوازم جانبی','other'=>'سایر'];
$accessories = ['sim'=>'سیم‌کارت','case'=>'قاب','charger'=>'شارژر','cable'=>'کابل','memory_card'=>'کارت حافظه','box'=>'جعبه','other'=>'سایر'];
$conditions = ['screen_cracked'=>'شکستگی نمایشگر','scratches'=>'خط و خش','frame_cracked'=>'شکستگی فریم','back_cracked'=>'شکستگی قاب پشت','water_damage'=>'آب‌خوردگی','impact'=>'اثر ضربه','opened_before'=>'بازشدگی قبلی','repaired_before'=>'تعمیر قبلی','other'=>'سایر'];
$intakeId = $parentRepair ? 'repair-warranty-intake-modal' : 'repair-intake-modal';
?>
<div class="modal pr-intake-modal" id="<?= e($intakeId) ?>" aria-hidden="true">
  <div class="modal-content pr-intake-dialog">
    <div class="modal-header"><div><small>Proma Pay · Repair</small><h2><?= $parentRepair ? 'پذیرش برگشت گارانتی' : 'پذیرش تعمیر جدید' ?></h2></div><button class="icon-btn" type="button" data-close-modal aria-label="بستن">×</button></div>
    <form class="pr-intake-form" method="post" enctype="multipart/form-data" action="<?= e(url('plugin/repair/create')) ?>" data-repair-intake-form>
      <div class="modal-body pr-intake-body">
        <?= csrf_field() ?>
        <?php if ($parentRepair): ?><input type="hidden" name="parent_repair_id" value="<?= (int) $parentRepair['parent_repair_id'] ?>"><?php endif; ?>
        <div class="pr-intake-section"><div class="pr-intake-section-title"><b>۰۱</b><div><h3>مشتری</h3><p>از فهرست مشتریان فعال Proma Pay جستجو و انتخاب کنید.</p></div></div>
          <div class="pr-grid two">
            <label class="pr-field">جستجوی مشتری <span class="proma-live-search" data-customer-live-search data-search-url="<?= e(url('plugin/repair/customer-lookup')) ?>">
              <input data-customer-search-input autocomplete="off" placeholder="نام، موبایل، کد ملی یا شناسه مشتری" value="<?= e($parentRepair['customer_name'] ?? '') ?>">
              <input type="hidden" name="customer[customer_id]" data-customer-select value="<?= (int) ($parentRepair['customer_id'] ?? 0) ?>" required>
              <span class="proma-live-results" data-customer-search-results hidden></span>
            </span></label>
            <div class="pr-intake-customer-actions"><?php if (\Auth::role() === 'admin'): ?><button class="btn secondary" type="button" data-customer-create-open data-open-modal="create-customer">افزودن مشتری جدید</button><?php endif; ?><span data-repair-selected-customer><?= $parentRepair ? e($parentRepair['customer_name'] . ' · ' . to_persian_digits($parentRepair['customer_mobile'] ?? '')) : 'مشتری انتخاب نشده است' ?></span></div>
          </div>
        </div>
        <div class="pr-intake-section"><div class="pr-intake-section-title"><b>۰۲</b><div><h3>دستگاه</h3><p>شناسه‌های دستگاه در اطلاعات داخلی پرونده نگهداری می‌شوند.</p></div></div>
          <div class="pr-grid four">
            <label class="pr-field">نوع دستگاه<select name="device[device_type]" required><?php foreach ($deviceTypes as $value=>$label): ?><option value="<?= e($value) ?>"<?= selected($parentRepair['device_type'] ?? 'mobile', $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="pr-field">برند<input name="device[brand]" value="<?= e($parentRepair['brand'] ?? '') ?>" maxlength="100"></label>
            <label class="pr-field">مدل<input name="device[model]" value="<?= e($parentRepair['model'] ?? '') ?>" maxlength="150" required></label>
            <label class="pr-field">رنگ<input name="device[color]" value="<?= e($parentRepair['color'] ?? '') ?>" maxlength="80"></label>
            <?php if (!empty($canAssign) && !empty($technicians)): ?><label class="pr-field pr-span-all">تعمیرکار مسئول<div class="pr-technician-picker" data-technician-picker><input type="search" data-technician-filter placeholder="نام تعمیرکار را برای جستجو بنویسید" autocomplete="off"><select name="technician_user_id" data-technician-select><option value="0">بعداً ارجاع می‌دهم</option><?php foreach ($technicians as $technician): ?><option value="<?= (int) $technician['user_id'] ?>" data-search="<?= e(mb_strtolower((string) $technician['full_name'], 'UTF-8')) ?>"><?= e($technician['full_name']) ?> · <?= e(to_persian_digits($technician['mobile'] ?? '')) ?></option><?php endforeach; ?></select></div></label><?php endif; ?>
          </div>
        </div>
        <div class="pr-intake-section"><div class="pr-intake-section-title"><b>۰۳</b><div><h3>وضعیت ظاهری و لوازم همراه</h3><p>موارد تحویل‌شده و آسیب‌های قابل مشاهده را ثبت کنید.</p></div></div>
          <strong class="pr-intake-subtitle">لوازم همراه</strong><div class="pr-checks"><?php foreach ($accessories as $value=>$label): ?><label class="pr-check"><input type="checkbox" name="device[accessories][]" value="<?= e($value) ?>"<?= checked(in_array($value, $parentAccessories, true), true) ?>><?= e($label) ?></label><?php endforeach; ?></div>
          <strong class="pr-intake-subtitle">وضعیت دستگاه</strong><div class="pr-checks"><?php foreach ($conditions as $value=>$label): ?><label class="pr-check"><input type="checkbox" name="device[condition_flags][]" value="<?= e($value) ?>"<?= checked(in_array($value, $parentConditions, true), true) ?>><?= e($label) ?></label><?php endforeach; ?></div>
          <label class="pr-field mt-3">یادداشت وضعیت<textarea name="device[condition_notes]" maxlength="2000"></textarea></label>
        </div>
        <div class="pr-intake-section"><div class="pr-intake-section-title"><b>۰۴</b><div><h3>شرح خرابی مشتری</h3><p>شرح مشتری جدا از تشخیص فنی تعمیرکار نگهداری می‌شود.</p></div></div>
          <label class="pr-field">شرح خرابی<textarea name="customer_report" maxlength="4000" required placeholder="مثلاً دستگاه روشن نمی‌شود."></textarea></label>
          <div class="pr-grid two pr-intake-meta-grid mt-3">
            <label class="pr-field">تاریخ تحویل تخمینی<input name="estimated_delivery_date" data-jalali-input autocomplete="off" placeholder="۱۴۰۵/۰۷/۰۹"></label>
            <label class="pr-field">ساعت تخمینی<input name="estimated_delivery_time" type="time" value="17:00"></label>
            <label class="pr-field">نوع قفل دستگاه<select name="device[password_type]" data-repair-secret-type><option value="">بدون رمز</option><option value="password">رمز</option><option value="pin">PIN</option><option value="pattern">پترن</option></select></label>
            <div class="pr-field" data-repair-secret-container><span data-repair-secret-label>رمز دستگاه</span><div class="pr-secret-input-row"><input name="device[device_password]" type="password" maxlength="128" autocomplete="new-password" data-repair-secret-input hidden><button class="btn icon-only secondary" type="button" data-secret-toggle aria-label="نمایش رمز" title="نمایش یا مخفی‌کردن رمز" hidden><?= proma_icon('eye', 'نمایش رمز') ?></button></div><button class="btn secondary" type="button" data-pattern-open data-open-modal="repair-pattern-modal" hidden>ثبت پترن</button><span class="pr-secret-confirmed" data-pattern-confirmed hidden>پترن ثبت شده است.</span><small>نوع قفل را انتخاب کنید تا ورودی رمز یا PIN باز شود؛ برای الگو «ثبت پترن» را بزنید. مقادیر رمزگذاری می‌شوند.</small></div>
          </div>
        </div>
        <div class="pr-intake-section"><div class="pr-intake-section-title"><b>۰۵</b><div><h3>توضیحات پذیرش</h3><p>مبالغ دریافتی و هزینه‌ها پس از تشکیل پرونده در بخش مالی ثبت می‌شوند.</p></div></div>
          <label class="pr-field">یادداشت داخلی پذیرش<input name="intake_note" maxlength="255" placeholder="اختیاری"></label>
        </div>
        <div class="pr-intake-section"><div class="pr-intake-section-title"><b>۰۶</b><div><h3>تصاویر اولیه</h3><p>اختیاری؛ تصاویر خصوصی پرونده پس از ثبت پیوست می‌شوند.</p></div></div>
          <div class="pr-grid three"><label class="pr-field">نمای جلو<input type="file" name="intake_images[front]" accept="image/jpeg,image/png,image/webp"></label><label class="pr-field">نمای پشت<input type="file" name="intake_images[back]" accept="image/jpeg,image/png,image/webp"></label><label class="pr-field">جزئیات خرابی<input type="file" name="intake_images[damage_detail]" accept="image/jpeg,image/png,image/webp"></label></div>
        </div>
        <div class="pr-intake-section pr-intake-review"><div class="pr-intake-section-title"><b>۰۷</b><div><h3>بازبینی نهایی</h3><p>اطلاعات مشتری، دستگاه و شرح خرابی را پیش از ثبت مرور کنید.</p></div></div><p>پس از ثبت، کد رهگیری ساخته می‌شود و تغییر وضعیت پرونده در تاریخچه ثبت خواهد شد.</p></div>
        <div class="pr-intake-error" data-repair-intake-error role="alert" hidden></div>
      </div>
      <div class="modal-footer pr-intake-footer"><button class="btn secondary" type="button" data-close-modal>انصراف</button><button class="btn secondary" type="submit" name="_intent" value="draft" formnovalidate>ذخیره پیش‌نویس</button><button class="btn primary" type="submit" name="_intent" value="register">ثبت پذیرش</button></div>
    </form>
  </div>
</div>
<div class="modal pr-pattern-modal" id="repair-pattern-modal" aria-hidden="true">
  <div class="modal-content"><div class="modal-header"><div><h3>ترسیم پترن دستگاه</h3><small>چهار نقطه یا بیشتر را به‌ترتیب انتخاب کنید.</small></div><button class="icon-btn" type="button" data-close-modal aria-label="بستن">×</button></div>
    <div class="modal-body"><div class="pr-pattern-stage" data-pattern-stage><svg class="pr-pattern-lines" viewBox="0 0 300 300" aria-hidden="true"><polyline data-pattern-line points=""></polyline></svg><div class="pr-pattern-grid" data-pattern-grid><?php for ($node=1; $node<=9; $node++): ?><button type="button" data-dot="<?= $node ?>" aria-label="نقطه <?= $node ?>"><span><?= $node ?></span></button><?php endfor; ?></div></div><p class="pr-pattern-status" data-pattern-status>برای رسم، نقطه اول را لمس کنید.</p></div>
    <div class="modal-footer"><button class="btn secondary" type="button" data-pattern-clear>پاک کردن</button><button class="btn secondary" type="button" data-pattern-redraw>رسم مجدد</button><button class="btn primary" type="button" data-pattern-confirm>تأیید پترن</button></div>
  </div>
</div>
<?php require __DIR__ . '/customer_modal.php'; ?>
