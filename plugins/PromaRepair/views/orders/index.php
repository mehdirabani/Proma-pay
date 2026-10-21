<div class="proma-repair">
  <header class="pr-hero"><span class="pr-hero-icon"><i data-feather="clipboard"></i></span><div class="pr-hero-copy"><small>Repair Orders</small><h2>پرونده‌های تعمیر</h2><p>جستجو بر اساس کد رهگیری، مشتری، IMEI، مدل یا شناسه پرونده</p></div><?php if ($canCreate): ?><div class="pr-hero-actions"><button class="btn primary" type="button" data-open-modal="repair-intake-modal"><i data-feather="plus"></i> پذیرش تعمیر</button></div><?php endif; ?></header>
  <section class="pr-card">
    <form class="pr-filter" method="get" action="<?= e(url('plugin/repair/orders')) ?>">
      <input type="hidden" name="route" value="plugin/repair/orders">
      <label class="pr-field"><span>جستجو</span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="کد رهگیری، موبایل، IMEI، مدل…"></label>
      <label class="pr-field"><span>وضعیت</span><select name="status"><option value="">همه وضعیت‌ها</option><?php foreach ($statusLabels as $status => $label): ?><option value="<?= e($status) ?>"<?= selected($filters['status'], $status) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label class="pr-field"><span>نوع دستگاه</span><select name="device_type"><option value="">همه دستگاه‌ها</option><?php foreach (['mobile'=>'موبایل','tablet'=>'تبلت','laptop'=>'لپ‌تاپ','smartwatch'=>'ساعت هوشمند','headphones'=>'هدفون','console'=>'کنسول','accessory'=>'لوازم جانبی','other'=>'سایر'] as $key=>$label): ?><option value="<?= e($key) ?>"<?= selected($filters['device_type'], $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label class="pr-field"><span>برند</span><input name="brand" value="<?= e($filters['brand']) ?>" placeholder="مثلاً Samsung"></label>
      <?php if ($technicians): ?><label class="pr-field"><span>تعمیرکار</span><select name="technician_id"><option value="">همه تعمیرکاران</option><?php foreach ($technicians as $technician): ?><option value="<?= (int) $technician['user_id'] ?>"<?= selected($filters['technician_id'], $technician['user_id']) ?>><?= e($technician['full_name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
      <label class="pr-field"><span>از تاریخ</span><input name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>" placeholder="۱۴۰۵/۰۱/۰۱"></label>
      <label class="pr-field"><span>تا تاریخ</span><input name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>" placeholder="۱۴۰۵/۱۲/۲۹"></label>
      <?php if ($canFinancial): ?><label class="pr-field"><span>وضعیت پرداخت</span><select name="payment_status"><option value="">همه</option><?php foreach (['unpaid'=>'پرداخت‌نشده','partial'=>'پرداخت ناقص','paid'=>'تسویه‌شده'] as $key=>$label): ?><option value="<?= e($key) ?>"<?= selected($filters['payment_status'], $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label><?php endif; ?>
      <?php if ($canProfit): ?><label class="pr-field"><span>سودآوری</span><select name="profitability"><option value="">همه</option><option value="profitable"<?= selected($filters['profitability'], 'profitable') ?>>سودده</option><option value="unprofitable"<?= selected($filters['profitability'], 'unprofitable') ?>>بدون سود</option></select></label><?php endif; ?>
      <label class="pr-field"><span>گارانتی</span><select name="warranty"><option value="">همه</option><option value="yes"<?= selected($filters['warranty'], 'yes') ?>>دارای گارانتی</option><option value="no"<?= selected($filters['warranty'], 'no') ?>>بدون گارانتی</option></select></label>
      <div class="pr-actions"><button class="btn primary" type="submit">اعمال فیلتر</button><a class="btn secondary" href="<?= e(url('plugin/repair/orders')) ?>">پاک‌کردن</a></div>
    </form>
  </section>
  <section class="pr-card">
    <div class="pr-card-head"><div><h3>نتایج</h3><p><?= to_persian_digits($result['total']) ?> پرونده</p></div></div>
    <?php if (!$result['items']): ?><div class="pr-empty">پرونده‌ای با این فیلتر پیدا نشد.</div>
    <?php else: ?><div class="pr-table-wrap"><table class="pr-table"><thead><tr><th scope="col">کد رهگیری</th><th scope="col">دستگاه</th><th scope="col">مشتری</th><th scope="col">تعمیرکار</th><th scope="col">وضعیت</th><th scope="col">تاریخ</th><th scope="col"></th></tr></thead><tbody>
      <?php foreach ($result['items'] as $item): ?><tr>
        <td data-label="کد رهگیری"><span class="pr-code"><?= e($item['tracking_code']) ?></span><small>#<?= to_persian_digits($item['id']) ?></small></td>
        <td data-label="دستگاه"><strong><?= e(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '')) ?: 'دستگاه') ?></strong><small><?= e($item['device_type']) ?></small></td>
        <td data-label="مشتری"><strong><?= e($item['customer_name']) ?></strong><small><?= e(to_persian_digits($item['customer_mobile'])) ?></small></td>
        <td data-label="تعمیرکار"><?= e($item['technician_name'] ?: 'ارجاع نشده') ?></td>
        <td data-label="وضعیت"><span class="pr-badge <?= in_array($item['status'], ['ready_for_delivery','delivered'], true) ? 'is-success' : 'is-info' ?>"><?= e($statusLabels[$item['status']] ?? '') ?></span></td>
        <td data-label="تاریخ"><?= e(jdatetime($item['created_at'])) ?></td>
        <td><a class="btn small secondary" href="<?= e(url('plugin/repair/detail/' . (int) $item['id'])) ?>">جزئیات</a></td>
      </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    <?php if ($result['pages'] > 1): ?><nav class="pr-actions mt-3" aria-label="صفحه‌بندی"><?php for ($p = 1; $p <= $result['pages']; $p++): ?><a class="btn small <?= $p === $result['page'] ? 'primary' : 'secondary' ?>" href="<?= e(url('plugin/repair/orders', array_merge($filters, ['date_from' => $_GET['date_from'] ?? '', 'date_to' => $_GET['date_to'] ?? '', 'page' => $p]))) ?>"><?= to_persian_digits($p) ?></a><?php endfor; ?></nav><?php endif; ?>
  </section>
</div>
<?php if ($canCreate): require __DIR__ . '/../intake/index.php'; endif; ?>
