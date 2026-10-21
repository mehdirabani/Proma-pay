<div class="proma-page-intro">
  <div>
    <span class="proma-eyebrow">پیگیری مشتریان</span>
    <h2>قراردادهای ارجاع‌شده</h2>
    <p>جزئیات قرارداد را ببینید، نتیجه تماس را ثبت کنید یا پرونده را برای بررسی حقوقی بفرستید.</p>
  </div>
</div>

<section class="proma-operator-contracts" aria-label="قراردادهای قابل پیگیری">
  <?php foreach ($contracts as $contract): ?>
    <?php $customerPhone = normalize_iran_phone($contract['mobile'] ?? ''); $contractId = (int) $contract['id']; $contactDirectory = $contractContacts[$contractId] ?? []; $contactDirectoryJson = json_encode($contactDirectory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>
    <article class="proma-operator-contract">
      <div class="proma-operator-contract__main">
        <div>
          <span class="proma-eyebrow"><?= e($contract['contract_number']) ?></span>
          <h3><?= e($contract['customer_name']) ?></h3>
          <p><?php if ($customerPhone !== ''): ?><?= to_persian_digits(format_iran_phone($contract['mobile'])) ?><?php else: ?><?= to_persian_digits($contract['mobile'] ?? '') ?><?php endif; ?><?= !empty($contract['secondary_phone']) ? '، ' . to_persian_digits($contract['secondary_phone']) : '' ?></p>
        </div>
        <div class="proma-operator-contract__amount">
          <strong><?= money_toman($contract['principal_amount']) ?></strong>
          <span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span>
        </div>
      </div>
      <div class="proma-operator-contract__actions">
        <?php if ($contactDirectory): ?><button class="btn small success proma-call-action" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($contract['contract_number']) ?>" aria-label="انتخاب شماره برای تماس با <?= e($contract['customer_name']) ?>"><i data-feather="phone-call"></i><span>تماس</span></button><?php endif; ?>
        <a class="btn small secondary" href="<?= e(url('contracts/show/' . (int) $contract['id'])) ?>"><i data-feather="eye"></i><span>جزئیات</span></a>
        <button class="btn small" type="button" data-open-modal="operator-call-<?= (int) $contract['id'] ?>"><i data-feather="phone-call"></i><span>ثبت تماس</span></button>
        <button class="btn small danger" type="button" data-open-modal="operator-legal-<?= (int) $contract['id'] ?>"><i data-feather="briefcase"></i><span>ارجاع حقوقی</span></button>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$contracts): ?><div class="empty">قراردادی برای پیگیری وجود ندارد.</div><?php endif; ?>
</section>

<section class="card proma-section-gap">
  <div class="card-header"><h2>گزارش تماس‌ها</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th scope="col">مشتری</th><th scope="col">قرارداد</th><th scope="col">نتیجه</th><th scope="col">پیگیری بعدی</th><th scope="col">یادداشت</th></tr></thead>
      <tbody>
      <?php foreach ($calls as $call): ?>
        <tr><td><?= e($call['customer_name']) ?></td><td><?= e($call['contract_number']) ?></td><td><?= e($call['call_result']) ?></td><td><?= e(jdate($call['next_followup_date'])) ?></td><td><?= e($call['notes']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$calls): ?><tr><td colspan="5" class="empty">تماسی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($contracts as $contract): ?>
  <?php $contractId = (int) $contract['id']; $contactDirectory = $contractContacts[$contractId] ?? []; $contactDirectoryJson = json_encode($contactDirectory, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>
  <div class="modal" id="operator-call-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>ثبت تماس با <?= e($contract['customer_name']) ?></h3><?php if ($contactDirectory): ?><button class="btn small success" type="button" data-open-modal="contact-directory" data-contact-directory="<?= e($contactDirectoryJson) ?>" data-contact-title="تماس‌های قرارداد <?= e($contract['contract_number']) ?>"><i data-feather="phone-call"></i><span>انتخاب شماره</span></button><?php endif; ?><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><i data-feather="x"></i></button></div>
      <form method="post" action="<?= e(url('operator/call')) ?>">
        <div class="modal-body form-grid two">
          <?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
          <label>نتیجه تماس <span class="required-mark" aria-hidden="true">*</span><input name="call_result" required data-modal-autofocus></label>
          <label>پیگیری بعدی<input name="next_followup_date" class="jalali-date" autocomplete="off" placeholder="انتخاب تاریخ"></label>
          <label class="full">یادداشت<textarea name="notes" rows="4"></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit"><i data-feather="check"></i><span>ثبت تماس</span></button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
      </form>
    </div>
  </div>
  <div class="modal" id="operator-legal-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>ارجاع <?= e($contract['contract_number']) ?> به حقوقی</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><i data-feather="x"></i></button></div>
      <form method="post" action="<?= e(url('operator/referLegal')) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
          <label>علت ارجاع <span class="required-mark" aria-hidden="true">*</span><input name="reason" required value="ارجاع اپراتور" data-modal-autofocus></label>
          <label>شرح پرونده<textarea name="notes" rows="5"></textarea></label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit"><i data-feather="send"></i><span>ارسال برای بررسی</span></button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
