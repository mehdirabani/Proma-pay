<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border"><h2>قراردادهایی که ضامنشان هستم</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>قرارداد</th><th>مشتری</th><th>تماس</th><th>وضعیت</th></tr></thead>
        <tbody>
        <?php foreach ($contracts as $contract): ?>
          <tr>
            <td><?= e($contract['contract_number']) ?></td>
            <td><?= e($contract['customer_name']) ?></td>
            <td><?= to_persian_digits($contract['mobile']) ?></td>
            <td><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$contracts): ?><tr><td colspan="4" class="empty">شما ضامن قراردادی نیستید.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><h2>ضامن‌های قراردادهای من</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>قرارداد</th><th>ضامن</th><th>تماس</th><th>کد ملی</th></tr></thead>
        <tbody>
        <?php foreach ($guarantors as $guarantor): ?>
          <tr>
            <td><?= e($guarantor['contract_number']) ?></td>
            <td><?= e($guarantor['full_name']) ?></td>
            <td><?= to_persian_digits($guarantor['mobile']) ?></td>
            <td><?= to_persian_digits($guarantor['national_id']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$guarantors): ?><tr><td colspan="4" class="empty">برای قراردادهای شما ضامنی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
