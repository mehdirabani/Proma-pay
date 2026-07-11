<?php
$isChannel = !empty($selectedChannel);
$activeChannelId = $isChannel ? (int) $selectedChannel['id'] : 0;
$hasSelection = $isChannel || (int) $contactId > 0;
$threadTitle = $isChannel ? $selectedChannel['title'] : 'رشته پیام';
$threadBadge = $isChannel ? 'کانال رسمی' : 'گفتگوی خصوصی';
?>
<div class="chat-layout messenger-layout">
  <section class="card messenger-contacts">
    <div class="card-header card-no-border"><h2>گفت‌وگوها</h2></div>
    <?php if (Auth::role() !== 'customer'): ?>
      <div class="card-body">
        <form method="get" action="<?= e(url('chat')) ?>" class="form-grid" data-chat-search-form>
          <input type="hidden" name="route" value="chat">
          <label class="full">شروع گفت‌وگو
            <span class="proma-live-search" data-user-live-search data-search-url="<?= e(url('users/search', ['roles' => 'admin,operator,lawyer,customer'])) ?>">
              <input data-user-search-input placeholder="نام، موبایل، کد ملی، نقش یا واحد">
              <input type="hidden" name="contact" data-user-id-input>
              <span class="proma-live-results" data-user-search-results hidden></span>
              <span class="proma-chip-row" data-user-chip></span>
            </span>
          </label>
          <div class="actions"><button class="icon-btn" type="submit" title="باز کردن گفت‌وگو"><i data-feather="message-square"></i></button></div>
        </form>
      </div>
    <?php endif; ?>
    <div class="card-body chat-list">
      <?php foreach ($contacts as $contact): ?>
        <?php
          $isChannelContact = ($contact['kind'] ?? '') === 'channel';
          $contactVerified = $isChannelContact && !empty($contact['is_verified']);
          $contactActive = $isChannelContact
              ? (int) ($contact['channel_id'] ?? 0) === $activeChannelId
              : (!$isChannel && (int) $contact['id'] === (int) $contactId);
          $contactHref = $isChannelContact
              ? url('chat', ['channel' => $contact['slug']])
              : url('chat', ['contact' => (int) $contact['id']]);
          $contactSubtitle = $isChannelContact
              ? 'کانال رسمی'
              : (Auth::role() === 'customer' ? department_label($contact['department'] ?? '') : role_label($contact['role']));
        ?>
        <a class="chat-contact <?= $contactActive ? 'active' : '' ?> <?= $isChannelContact ? 'channel' : '' ?>" href="<?= e($contactHref) ?>">
          <span class="proma-risk-avatar"><?= e($isChannelContact ? 'ا' : mb_substr($contact['full_name'], 0, 1, 'UTF-8')) ?></span>
          <span>
            <strong>
              <span class="chat-contact-name"><?= e($contact['full_name']) ?></span>
              <?php if ($contactVerified): ?><span class="proma-verified-badge" title="کانال رسمی"><i data-feather="check"></i></span><?php endif; ?>
            </strong>
            <small><?= e($contactSubtitle) ?><?php if (!empty($contact['is_pinned'])): ?> · سنجاق‌شده<?php endif; ?></small>
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
        <h2 class="chat-thread-title"><?= e($threadTitle) ?><?php if ($isChannel): ?><span class="proma-verified-badge" title="کانال رسمی"><i data-feather="check"></i></span><?php endif; ?></h2>
        <span class="badge badge-light-info"><?= e($threadBadge) ?></span>
      </div>
    </div>
    <div class="chat-history" data-chat-history>
      <?php foreach ($messages as $message): ?>
        <?php
          $messageClasses = ['message'];
          if ((int) ($message['is_system'] ?? 0) === 1) {
              $messageClasses[] = 'system';
          } elseif ((int) $message['sender_id'] === (int) Auth::id()) {
              $messageClasses[] = 'mine';
          }
        ?>
        <div class="<?= e(implode(' ', $messageClasses)) ?>" data-id="<?= (int) $message['id'] ?>">
          <div><?= nl2br(e($message['body'])) ?></div>
          <?php if (!empty($message['attachment_id'])): ?>
            <?php if (!empty($message['attachment_path'])): ?>
              <a class="chat-attachment" href="<?= e(url('chat/attachment/' . $message['attachment_id'])) ?>" target="_blank">
                <img src="<?= e(url('chat/attachment/' . $message['attachment_id'])) ?>" alt="پیوست تصویر">
              </a>
            <?php else: ?>
              <span class="badge muted">فایل بررسی و حذف شد</span>
            <?php endif; ?>
          <?php endif; ?>
          <small class="message-meta">
            <?php if (!empty($message['sender_name'])): ?><span class="message-meta-name"><?= e($message['sender_name']) ?></span><?php endif; ?>
            <?php if ((int) ($message['is_system'] ?? 0) === 1): ?><span class="proma-verified-badge" title="حساب رسمی"><i data-feather="check"></i></span><?php endif; ?>
            <?php if (!empty($message['target_unit']) && (int) ($message['is_system'] ?? 0) !== 1 && (string) ($message['target_unit'] ?? '') !== (string) ($message['sender_name'] ?? '')): ?><span class="message-meta-unit"><?= e($message['target_unit']) ?></span><?php endif; ?>
            <span class="message-meta-time"><?= e(jdatetime($message['created_at'])) ?></span>
          </small>
        </div>
      <?php endforeach; ?>
      <?php if (!$hasSelection): ?><div class="empty">برای شروع، یک مخاطب را انتخاب کنید.</div><?php endif; ?>
      <?php if ($hasSelection && !$messages): ?><div class="empty">هنوز پیامی در این گفت‌وگو ثبت نشده است.</div><?php endif; ?>
    </div>
    <?php if ($hasSelection): ?>
      <?php if ($isChannel && empty($canSendChannel)): ?>
        <div class="chat-compose chat-readonly">این کانال برای اطلاع‌رسانی عمومی است و فقط تیم داخلی می‌تواند پیام ارسال کند.</div>
      <?php else: ?>
        <form class="chat-compose" method="post" action="<?= e(url('chat/send')) ?>" enctype="multipart/form-data" data-chat-form data-user-id="<?= (int) Auth::id() ?>" data-fetch-url="<?= e(url('chat/fetch', $isChannel ? ['channel' => $channelSlug] : ['contact' => $contactId])) ?>" data-attachment-url-template="<?= e(url('chat/attachment/__ID__')) ?>">
          <?= csrf_field() ?>
          <?php if ($isChannel): ?>
            <input type="hidden" name="channel_id" value="<?= (int) $selectedChannel['id'] ?>">
          <?php else: ?>
            <input type="hidden" name="receiver_id" value="<?= (int) $contactId ?>">
          <?php endif; ?>
          <input name="body" placeholder="متن پیام">
          <label class="btn secondary icon-only" title="ارسال تصویر" aria-label="ارسال تصویر"><i data-feather="image"></i><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden></label>
          <button class="btn" type="submit">ارسال</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<?php if (Auth::role() === 'admin' && !empty($pendingAttachments)): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>پیوست‌های چت در انتظار بررسی</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>فرستنده</th><th>گیرنده</th><th>پیام</th><th>فایل</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($pendingAttachments as $attachment): ?>
        <tr>
          <td><?= e($attachment['sender_name']) ?></td>
          <td><?= e($attachment['receiver_name']) ?></td>
          <td><?= e($attachment['body'] ?: 'تصویر بدون متن') ?></td>
          <td><a class="btn small secondary" href="<?= e(url('chat/attachment/' . $attachment['id'])) ?>" target="_blank">مشاهده</a></td>
          <td class="actions">
            <form method="post" action="<?= e(url('chat/approveAttachment/' . $attachment['id'])) ?>"><?= csrf_field() ?><button class="btn small success" type="submit">تأیید</button></form>
            <form method="post" action="<?= e(url('chat/rejectAttachment/' . $attachment['id'])) ?>"><?= csrf_field() ?><input name="review_note" placeholder="علت رد"><button class="btn small danger" type="submit">رد</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
