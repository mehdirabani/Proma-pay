<?php
$status = $status ?? 'pending';
$labels = ['paid' => 'موفق', 'pending' => 'در انتظار', 'failed' => 'ناموفق', 'cancelled' => 'لغوشده'];
?><span class="proma-zp-badge is-<?= e($status) ?>"><?= e($labels[$status] ?? $status) ?></span>
