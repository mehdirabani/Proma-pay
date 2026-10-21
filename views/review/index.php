<?php
$activeTab = $activeTab ?? 'identity';
$identityPendingCount = (int) ($identityPendingCount ?? count($identityRequests ?? []));
$receiptPendingCount = (int) ($receiptPendingCount ?? count($receiptRequests ?? []));
?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>بررسی موارد ارسالی</h2>
      <span class="badge muted">مدارک و رسیدهای در انتظار بررسی</span>
    </div>
  </div>
  <div class="card-body">
    <nav class="proma-settings-tabs" aria-label="نوع موارد ارسالی">
      <a class="tab-link <?= $activeTab === 'identity' ? 'active' : '' ?>" href="<?= e(url('review', ['tab' => 'identity'])) ?>">مدارک هویتی مشتریان <span class="badge badge-light-danger"><?= to_persian_digits($identityPendingCount) ?></span></a>
      <a class="tab-link <?= $activeTab === 'receipts' ? 'active' : '' ?>" href="<?= e(url('review', ['tab' => 'receipts'])) ?>">رسیدهای اقساط مشتریان <span class="badge badge-light-danger"><?= to_persian_digits($receiptPendingCount) ?></span></a>
    </nav>
  </div>
</section>

<div class="proma-review-summary" aria-label="موارد در انتظار بررسی">
  <article><span class="proma-review-summary__icon is-identity"><?= proma_icon('user') ?></span><span><small>مدارک هویتی در انتظار بررسی</small><strong><?= to_persian_digits($identityPendingCount) ?></strong></span></article>
  <article><span class="proma-review-summary__icon is-receipt"><?= proma_icon('file') ?></span><span><small>رسیدهای پرداخت در انتظار بررسی</small><strong><?= to_persian_digits($receiptPendingCount) ?></strong></span></article>
</div>

<?php if ($activeTab === 'identity'): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>مدارک هویتی در انتظار بررسی</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th scope="col">مشتری</th><th scope="col">نوع مدرک</th><th scope="col">زمان بارگذاری</th><th scope="col">فایل</th><th scope="col">توضیح مدیریت</th><th scope="col">عملیات</th></tr></thead>
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

<?php if ($activeTab === 'receipts'): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>رسیدهای کارت به کارت در انتظار بررسی</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th scope="col">مشتری</th><th scope="col">قرارداد</th><th scope="col">قسط</th><th scope="col">مبلغ اعلامی</th><th scope="col">مبلغ تأییدشده</th><th scope="col">تاریخ ارسال</th><th scope="col">رسید</th><th scope="col">توضیح مدیریت</th><th scope="col">عملیات</th></tr></thead>
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
