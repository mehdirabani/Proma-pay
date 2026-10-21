<?php
$accountingBalance = is_array($accountingBalance ?? null) ? $accountingBalance : null;
if ($accountingBalance):
  $balanceTone = (string) ($accountingBalance['tone'] ?? 'neutral');
  $balanceLabel = (string) ($accountingBalance['label'] ?? 'تسویه‌شده');
  $balanceAmount = money_toman($accountingBalance['absolute_balance'] ?? 0);
  $balanceHref = function_exists('url') ? url('plugin/accounting/my-finance') : '#';
?>
<div class="col-12">
  <section class="proma-account-balance-card proma-account-balance-card--<?= e($balanceTone) ?>" aria-labelledby="account-balance-title">
    <div class="proma-account-balance-card__icon" aria-hidden="true"><i data-feather="credit-card"></i></div>
    <div class="proma-account-balance-card__body">
      <span class="proma-eyebrow" id="account-balance-title">موجودی حساب شما</span>
      <strong><?= e($balanceAmount) ?></strong>
      <span class="proma-account-balance-card__status"><?= e($balanceLabel) ?></span>
      <small>مانده بر اساس اسناد قطعی دفترکل حسابداری</small>
    </div>
    <a class="btn secondary" href="<?= e($balanceHref) ?>"><i data-feather="bar-chart-2" aria-hidden="true"></i> جزئیات مالی</a>
  </section>
</div>
<?php endif; ?>
