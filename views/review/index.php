<?php
$activeTab = $activeTab ?? 'identity';
$identityPendingCount = (int) ($identityPendingCount ?? count($identityRequests ?? []));
$receiptPendingCount = (int) ($receiptPendingCount ?? count($receiptRequests ?? []));
$profilePendingCount = (int) ($profilePendingCount ?? 0);
?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>بررسی موارد ارسالی</h2>
      <span class="badge muted">مدارک و رسیدهای در انتظار بررسی</span>
    </div>
  </div>
  <div class="card-body">
    <nav class="proma-settings-tabs">
      <a class="tab-link <?= $activeTab === 'identity' ? 'active' : '' ?>" href="<?= e(url('review', ['tab' => 'identity'])) ?>">مدارک هویتی مشتریان <span class="badge badge-light-danger"><?= to_persian_digits($identityPendingCount) ?></span></a>
      <a class="tab-link <?= $activeTab === 'receipts' ? 'active' : '' ?>" href="<?= e(url('review', ['tab' => 'receipts'])) ?>">رسیدهای اقساط مشتریان <span class="badge badge-light-danger"><?= to_persian_digits($receiptPendingCount) ?></span></a>
      <a class="tab-link <?= $activeTab === 'profile' ? 'active' : '' ?>" href="<?= e(url('review', ['tab' => 'profile', 'status' => 'pending'])) ?>">اصلاح مشخصات <span class="badge badge-light-danger"><?= to_persian_digits($profilePendingCount) ?></span></a>
    </nav>
  </div>
</section>

<?php if ($activeTab === 'identity'): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>مدارک هویتی در انتظار بررسی</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>مشتری</th><th>نوع مدرک</th><th>زمان بارگذاری</th><th>فایل</th><th>توضیح مدیریت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($identityRequests as $document): ?>
        <tr>
          <td><?= e($document['full_name']) ?><br><span class="badge muted"><?= to_persian_digits($document['mobile']) ?></span></td>
          <td><?= e(IdentityDocument::typeLabel($document['document_type'])) ?></td>
          <td><?= e(jdatetime($document['uploaded_at'])) ?></td>
          <td><a class="btn small secondary" href="<?= e(url('profile/identityFile/' . $document['id'])) ?>" target="_blank">مشاهده</a></td>
          <td>
            <form id="identity-approve-<?= (int) $document['id'] ?>" method="post" action="<?= e(url('profile/identityApprove/' . $document['id'])) ?>">
              <?= csrf_field() ?>
              <input name="review_note" placeholder="توضیح اختیاری">
            </form>
            <form id="identity-reject-<?= (int) $document['id'] ?>" method="post" action="<?= e(url('profile/identityReject/' . $document['id'])) ?>">
              <?= csrf_field() ?>
              <input name="review_note" placeholder="علت رد">
            </form>
          </td>
          <td class="actions">
            <button class="btn small success" type="submit" form="identity-approve-<?= (int) $document['id'] ?>">تأیید</button>
            <button class="btn small danger" type="submit" form="identity-reject-<?= (int) $document['id'] ?>">رد</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($identityRequests)): ?><tr><td colspan="6" class="empty">مدرک هویتی در انتظار بررسی وجود ندارد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php if ($activeTab === 'profile'): ?>
<section class="card proma-profile-review">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>درخواست‌های اصلاح مشخصات</h2>
      <form method="get" action="<?= e(url('review')) ?>" class="proma-filter-row">
        <input type="hidden" name="route" value="review">
        <input type="hidden" name="tab" value="profile">
        <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="نام، موبایل یا کد ملی">
        <select name="role">
          <option value="">همه نقش‌ها</option>
          <?php foreach (['admin', 'operator', 'lawyer', 'customer'] as $role): ?><option value="<?= e($role) ?>"<?= selected($_GET['role'] ?? '', $role) ?>><?= e(role_label($role)) ?></option><?php endforeach; ?>
        </select>
        <select name="status">
          <?php foreach (['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'partial' => 'تأیید جزئی', 'rejected' => 'ردشده', '' => 'همه'] as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($profileStatus ?? 'pending', $value) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <button class="btn small secondary" type="submit"><i data-feather="filter"></i> اعمال</button>
      </form>
    </div>
  </div>
  <div class="card-body proma-profile-review-list">
    <?php foreach (($profileRequests ?? []) as $request): ?>
      <?php $requested = $request['requested_fields'] ?? []; $conflicts = $request['conflict_fields'] ?? []; $isPending = ($request['status'] ?? '') === 'pending'; ?>
      <article class="proma-review-request">
        <header>
          <div><strong><?= e($request['full_name'] ?? '') ?></strong><small><?= e(role_label($request['role'] ?? '')) ?> · <?= e(to_persian_digits($request['mobile'] ?? '')) ?></small></div>
          <span class="badge <?= e(badge_class($request['status'] ?? 'pending')) ?>"><?= e(ProfileRequest::statusLabel($request['status'] ?? 'pending')) ?></span>
        </header>
        <form method="post" action="<?= e(url('profile/approve/' . (int) $request['id'])) ?>" class="proma-review-fields">
          <?= csrf_field() ?>
          <?php foreach ($requested as $field => $value): ?>
            <?php $hasConflict = in_array($field, $conflicts, true); ?>
            <label class="proma-review-field <?= $hasConflict ? 'has-conflict' : '' ?>">
              <?php if ($isPending): ?><input type="checkbox" name="approved_fields[]" value="<?= e($field) ?>"<?= $hasConflict ? ' disabled' : ' checked' ?>><?php endif; ?>
              <span><small><?= e(ProfileRequest::fieldLabels()[$field] ?? $field) ?></small><del><?= e($request['current_' . $field] ?? '') ?></del><strong><?= e($value) ?></strong></span>
              <?php if ($hasConflict): ?><em>مقدار فعلی پس از ثبت درخواست تغییر کرده است.</em><?php endif; ?>
            </label>
          <?php endforeach; ?>
          <?php if ($isPending): ?>
            <label class="full">یادداشت بررسی<textarea name="review_notes" rows="2"></textarea></label>
            <div class="proma-review-actions"><button class="btn small primary" type="submit"><i data-feather="check"></i> ثبت نتیجه انتخاب‌ها</button></div>
          <?php elseif (!empty($request['review_notes'])): ?><p class="notice info"><?= e($request['review_notes']) ?></p><?php endif; ?>
        </form>
      </article>
    <?php endforeach; ?>
    <?php if (empty($profileRequests)): ?><div class="empty">درخواستی با این فیلتر پیدا نشد.</div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($activeTab === 'receipts'): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>رسیدهای کارت به کارت در انتظار بررسی</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>مشتری</th><th>قرارداد</th><th>قسط</th><th>مبلغ اعلامی</th><th>مبلغ تأییدشده</th><th>تاریخ ارسال</th><th>رسید</th><th>توضیح مدیریت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($receiptRequests as $receipt): ?>
        <tr>
          <td><?= e($receipt['customer_name']) ?><br><span class="badge muted"><?= to_persian_digits($receipt['mobile']) ?></span></td>
          <td><?= e($receipt['contract_number']) ?></td>
          <td><?= to_persian_digits($receipt['installment_number']) ?></td>
          <td><?= money_toman($receipt['amount']) ?></td>
          <td>
            <input name="approved_amount" data-money form="receipt-approve-<?= (int) $receipt['id'] ?>" value="<?= e(number_format((float) $receipt['amount'], 0)) ?>" required>
          </td>
          <td><?= e(jdatetime($receipt['submitted_at'])) ?></td>
          <td><a class="btn small secondary" href="<?= e(url('payments/receiptFile/' . $receipt['id'])) ?>" target="_blank">مشاهده</a></td>
          <td>
            <form id="receipt-approve-<?= (int) $receipt['id'] ?>" method="post" action="<?= e(url('payments/approveReceipt/' . $receipt['id'])) ?>">
              <?= csrf_field() ?>
              <input name="review_note" placeholder="توضیح اختیاری">
            </form>
            <form id="receipt-reject-<?= (int) $receipt['id'] ?>" method="post" action="<?= e(url('payments/rejectReceipt/' . $receipt['id'])) ?>">
              <?= csrf_field() ?>
              <input name="review_note" placeholder="علت رد">
            </form>
          </td>
          <td class="actions">
            <button class="btn small success" type="submit" form="receipt-approve-<?= (int) $receipt['id'] ?>">تأیید</button>
            <button class="btn small danger" type="submit" form="receipt-reject-<?= (int) $receipt['id'] ?>">رد</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($receiptRequests)): ?><tr><td colspan="9" class="empty">رسید پرداخت در انتظار بررسی وجود ندارد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
