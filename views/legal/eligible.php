<?php $isLawyer = Auth::role() === 'lawyer'; ?>
<section class="card">
  <div class="card-header card-no-border"><div class="header-top"><div><h2>قراردادهای واجد شرایط بررسی حقوقی</h2><small class="text-muted">قراردادهایی که طبق سیاست جاری شرایط بررسی حقوقی را دارند و هنوز پرونده فعال ندارند.</small></div><span class="badge badge-light-warning"><?= to_persian_digits(count($contracts ?? [])) ?> قرارداد</span></div></div>
  <div class="card-body pt-0">
    <nav class="proma-contract-tabs" aria-label="صف پرونده‌های حقوقی"><a href="<?= e(url('legal')) ?>">پرونده‌های حقوقی</a><a href="<?= e(url('legal/referred')) ?>">پرونده‌های ارجاع‌شده</a><a class="active" href="<?= e(url('legal/eligible')) ?>">واجد شرایط بررسی حقوقی</a></nav>
    <form method="get" action="<?= e(url('legal/eligible')) ?>" class="form-grid three"><input type="hidden" name="route" value="legal/eligible"><label>جستجوی مشتری یا قرارداد<input name="q" value="<?= e($search ?? '') ?>" placeholder="نام، موبایل یا شماره قرارداد"></label><div class="actions"><button class="btn secondary" type="submit">جستجو</button></div></form>
    <div class="table-wrap"><table><thead><tr><th scope="col">قرارداد</th><th scope="col">مشتری</th><th scope="col">قدیمی‌ترین سررسید</th><th scope="col">روز تأخیر</th><th scope="col">تعداد اقساط</th><th scope="col">مبلغ معوق</th><th scope="col">عملیات</th></tr></thead><tbody>
      <?php foreach (($contracts ?? []) as $contract): ?><tr>
        <td><a href="<?= e(url('contracts/show/' . (int) $contract['id'])) ?>"><?= e($contract['contract_number']) ?></a></td>
        <td><?= e($contract['customer_name']) ?><br><a href="tel:<?= e(to_english_digits(preg_replace('/[^0-9۰-۹٠-٩+]/u', '', (string) ($contract['mobile'] ?? '')))) ?>"><?= e(to_persian_digits($contract['mobile'] ?? '')) ?></a></td>
        <td><?= !empty($contract['oldest_due_date']) ? e(jdate($contract['oldest_due_date'])) : '—' ?></td>
        <td><?= to_persian_digits($contract['delay_days'] ?? 0) ?></td><td><?= to_persian_digits($contract['overdue_count'] ?? 0) ?></td><td><?= money_toman($contract['overdue_amount'] ?? 0) ?></td>
        <td><form method="post" action="<?= e(url($isLawyer ? 'lawyer/create' : 'legal/create')) ?>"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>"><?php if ($isLawyer): ?><input type="hidden" name="legal_case_request_uuid" value="<?= e(bin2hex(random_bytes(16))) ?>"><?php else: ?><input type="hidden" name="stage" value="ارجاع اولیه"><?php endif; ?><button class="btn small primary" type="submit"><?= $isLawyer ? 'ایجاد پرونده برای خودم' : 'ارجاع به حقوقی' ?></button></form></td>
      </tr><?php endforeach; ?>
      <?php if (empty($contracts)): ?><tr><td colspan="7" class="empty">قرارداد واجد شرایطِ بدون پروندهٔ فعال پیدا نشد.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>
