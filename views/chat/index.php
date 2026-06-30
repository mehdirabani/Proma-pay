<div class="chat-layout messenger-layout">
  <section class="card messenger-contacts">
    <div class="card-header card-no-border"><h2>گفت‌وگوها</h2></div>
    <div class="card-body chat-list">
      <?php foreach ($contacts as $contact): ?>
        <a class="chat-contact <?= (int) $contact['id'] === (int) $contactId ? 'active' : '' ?>" href="<?= e(url('chat', ['contact' => $contact['id']])) ?>">
          <span class="proma-risk-avatar"><?= e(mb_substr($contact['full_name'], 0, 1, 'UTF-8')) ?></span>
          <span>
            <strong><?= e($contact['full_name']) ?></strong>
            <small><?= e(Auth::role() === 'customer' ? department_label($contact['department'] ?? '') : role_label($contact['role'])) ?></small>
          </span>
          <?php if ((int) ($contact['unread_count'] ?? 0) > 0): ?><em><?= to_persian_digits($contact['unread_count']) ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if (!$contacts): ?><div class="empty">مخاطبی برای گفت‌وگو وجود ندارد.</div><?php endif; ?>
    </div>
  </section>

  <section class="card chat-box">
    <div class="card-header card-no-border">
      <div class="header-top">
        <h2>رشته پیام</h2>
        <span class="badge badge-light-info">به‌روزرسانی خودکار</span>
      </div>
    </div>
    <div class="chat-history" data-chat-history>
      <?php foreach ($messages as $message): ?>
        <div class="message <?= (int) $message['sender_id'] === (int) Auth::id() ? 'mine' : '' ?>" data-id="<?= (int) $message['id'] ?>">
          <div><?= nl2br(e($message['body'])) ?></div>
          <small><?= e($message['sender_name']) ?>، <?= e(jdatetime($message['created_at'])) ?></small>
        </div>
      <?php endforeach; ?>
      <?php if (!$contactId): ?><div class="empty">برای شروع، یک مخاطب را انتخاب کنید.</div><?php endif; ?>
      <?php if ($contactId && !$messages): ?><div class="empty">هنوز پیامی در این گفت‌وگو ثبت نشده است.</div><?php endif; ?>
    </div>
    <?php if ($contactId): ?>
    <form class="chat-compose" method="post" action="<?= e(url('chat/send')) ?>" data-chat-form data-user-id="<?= (int) Auth::id() ?>" data-fetch-url="<?= e(url('chat/fetch', ['contact' => $contactId])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="receiver_id" value="<?= (int) $contactId ?>">
      <input name="body" placeholder="متن پیام">
      <button class="btn" type="submit">ارسال</button>
    </form>
    <?php endif; ?>
  </section>
</div>
