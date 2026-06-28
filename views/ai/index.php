<section class="card">
  <div class="card-header"><h2>تحلیل متن فارسی</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('ai/analyze')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <label class="full">متن برای تحلیل<textarea name="text" required><?= e($text ?? '') ?></textarea></label>
      <div class="full"><button class="btn" type="submit">شروع تحلیل</button></div>
    </form>
  </div>
</section>

<?php if ($analysis): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>نتیجه تحلیل</h2></div>
  <div class="card-body">
    <?php if ($analysis['ok']): ?>
      <div style="white-space:pre-wrap"><?= e($analysis['content']) ?></div>
    <?php else: ?>
      <div class="notice error"><?= e($analysis['error']) ?></div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
