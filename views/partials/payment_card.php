<?php
$paymentCardLogoText = trim((string) ($cardTransferBankLogoText ?? ''));
$paymentCardBankName = trim((string) ($cardTransferBankName ?? ''));
$paymentCardHolderName = trim((string) ($cardTransferAccountName ?? ''));
$paymentCardNumber = trim((string) ($cardTransferCardNumber ?? ''));
$paymentCardSheba = trim((string) ($cardTransferSheba ?? ''));
$paymentCardAccountNumber = trim((string) ($cardTransferAccountNumber ?? ''));
$paymentCardShowSheba = (string) ($cardTransferShowSheba ?? '0') === '1';
$paymentCardShowAccountNumber = (string) ($cardTransferShowAccountNumber ?? '0') === '1';
$paymentCardQrPayload = trim((string) ($cardTransferQrPayloadB64 ?? ''));
$paymentCardPrimary = sanitize_hex_color($cardTransferPrimaryColor ?? '#7366ff', '#7366ff');
$paymentCardSecondary = sanitize_hex_color($cardTransferSecondaryColor ?? '#16c7f9', '#16c7f9');
$paymentCardDownloadName = trim((string) ($paymentCardDownloadName ?? 'proma-payment-qr.html'));
?>
<section
  class="proma-payment-card"
  dir="rtl"
  data-payment-card
  data-payment-qr-payload-b64="<?= e($paymentCardQrPayload) ?>"
  data-payment-qr-download-name="<?= e($paymentCardDownloadName !== '' ? $paymentCardDownloadName : 'proma-payment-qr.html') ?>"
  style="--payment-card-primary: <?= e($paymentCardPrimary) ?>; --payment-card-secondary: <?= e($paymentCardSecondary) ?>;"
>
  <div class="proma-payment-card__header">
    <div class="proma-payment-card__brand">
      <div class="proma-payment-card__logo"><?= e($paymentCardLogoText !== '' ? $paymentCardLogoText : 'پ') ?></div>
      <div>
        <small>بانک</small>
        <strong><?= e($paymentCardBankName !== '' ? $paymentCardBankName : 'اطلاعات پرداخت') ?></strong>
      </div>
    </div>
    <div class="proma-payment-card__chip" aria-hidden="true"></div>
  </div>

  <div class="proma-payment-card__number-wrap">
    <small>شماره کارت</small>
    <strong class="proma-payment-card__number"><?= e($paymentCardNumber !== '' ? to_persian_digits(format_card_number($paymentCardNumber)) : 'ثبت نشده') ?></strong>
  </div>

  <div class="proma-payment-card__holder">
    <small>نام صاحب حساب</small>
    <strong><?= e($paymentCardHolderName !== '' ? $paymentCardHolderName : 'ثبت نشده') ?></strong>
  </div>

  <div class="proma-payment-card__details">
    <?php if ($paymentCardShowSheba && $paymentCardSheba !== ''): ?>
      <div class="proma-payment-card__detail">
        <small>شماره شبا</small>
        <strong><?= e(to_persian_digits(format_sheba($paymentCardSheba))) ?></strong>
      </div>
    <?php endif; ?>
    <?php if ($paymentCardShowAccountNumber && $paymentCardAccountNumber !== ''): ?>
      <div class="proma-payment-card__detail">
        <small>شماره حساب</small>
        <strong><?= e(to_persian_digits(format_account_number($paymentCardAccountNumber))) ?></strong>
      </div>
    <?php endif; ?>
  </div>

  <div class="proma-payment-card__actions">
    <?php if ($paymentCardNumber !== ''): ?>
      <button class="proma-payment-card__copy" type="button" data-copy-shortcode="<?= e($paymentCardNumber) ?>" data-copy-message="شماره کارت کپی شد.">
        <i data-feather="copy"></i>
        <span>کپی شماره کارت</span>
      </button>
    <?php endif; ?>
    <?php if ($paymentCardShowSheba && $paymentCardSheba !== ''): ?>
      <button class="proma-payment-card__copy" type="button" data-copy-shortcode="<?= e($paymentCardSheba) ?>" data-copy-message="شماره شبا کپی شد.">
        <i data-feather="copy"></i>
        <span>کپی شماره شبا</span>
      </button>
    <?php endif; ?>
    <?php if ($paymentCardShowAccountNumber && $paymentCardAccountNumber !== ''): ?>
      <button class="proma-payment-card__copy" type="button" data-copy-shortcode="<?= e($paymentCardAccountNumber) ?>" data-copy-message="شماره حساب کپی شد.">
        <i data-feather="copy"></i>
        <span>کپی شماره حساب</span>
      </button>
    <?php endif; ?>
  </div>

  <?php if ($paymentCardQrPayload !== ''): ?>
    <div class="proma-payment-card__qr">
      <div class="proma-payment-card__qr-grid" data-payment-qr-grid aria-label="QR پرداخت"></div>
      <div class="proma-payment-card__qr-meta">
        <div>
          <small>QR پرداخت</small>
          <strong>برای اسکن یا دانلود</strong>
        </div>
        <div class="proma-payment-card__qr-actions">
          <button class="proma-payment-card__copy" type="button" data-payment-qr-download>
            <i data-feather="download"></i>
            <span>دانلود QR پرداخت</span>
          </button>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>
