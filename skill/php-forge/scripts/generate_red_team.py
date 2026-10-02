#!/usr/bin/env python3
from pathlib import Path
import json,random
BASE=Path(__file__).resolve().parent.parent;random.seed(1207)
# Built after development design; executed only after runtime freeze. Expectations are broad domain requirements,
# not exact routes, so this set probes semantic generalization without becoming a second tuning dataset.
CASES=[
('The post-login return address is accepted from the request and can point to another domain.','security'),
('Our XML import accepts a document type that resolves remote entities from attacker controlled input.','security'),
('A profile update binds arbitrary request fields, including the privileged role flag.','security'),
('A resize option from the request is concatenated into the shell command used by the worker.','security'),
('Changing the numeric invoice key reveals a different customer account without an ownership check.','security'),
('A password recovery code has so little entropy that support can guess another user code.','security'),
('The upload endpoint validates the filename but still permits executable server-side files.','security'),
('A path parameter containing dot-dot segments can escape the intended export directory.','security'),
('A callback signature is optional on one retry path, so forged events can mutate order state.','security'),
('The same signed webhook can be replayed later because freshness and event identity are never checked.','security'),
('Serialized request data is restored into objects before the caller is trusted.','security'),
('A redirect target is normalized after validation, allowing the final destination to escape the allowlist.','security'),
('A cached permission decision lets a revoked operator keep modifying another tenant for several minutes.','security'),
('An error response includes the database credential that was loaded by the connector.','security'),
('The dependency installer executes a package hook pulled from an untrusted fork during CI.','security'),
('کاربر با عوض کردن شماره فاکتور می‌تواند فاکتور مشتری دیگری را باز کند.','security'),
('ورودی فرم بدون جدا کردن آرگومان‌ها مستقیم به دستور سیستم وصل شده است.','security'),
('کد بازیابی رمز کوتاه است و می‌شود با چند حدس پیدایش کرد.','security'),
('فایل آپلودی فقط از روی پسوند بررسی می‌شود و PHP هم قبول می‌شود.','security'),
('امضای callback در یکی از مسیرهای retry بررسی نمی‌شود.','security'),
('user mitone ba avaz kardan invoice id etelaate moshtarie dige ro bebine','security'),
('return url ro user mide va mishe ferestad be domain khareji','security'),
('The balance check and debit commit are separate, so two simultaneous withdrawals can both succeed.','fintech'),
('A gateway retry with the same provider reference creates a second charge record.','fintech'),
('A late authorization event can move an already captured transaction back to pending.','fintech'),
('The processor succeeded but our request timed out; retrying the command can debit the customer again.','fintech'),
('Several partial refunds can add up to more than the captured amount.','fintech'),
('Settlement includes transactions that are only authorized and not yet captured.','fintech'),
('Reconciliation cannot match one provider reference because it was reused for two internal attempts.','fintech'),
('Currency exponent is ignored and the service treats JPY like a two-decimal currency.','fintech'),
('A credit limit is checked before an async reservation, allowing concurrent purchases to exceed it.','fintech'),
('An installment can transition from overdue directly to paid without recording the received amount.','fintech'),
('Out-of-order gateway events overwrite a newer settled state with an older pending state.','fintech'),
('A compensating refund may run twice when the queue redelivers the failure event.','fintech'),
('دو برداشت همزمان هر دو موجودی قبلی را معتبر می‌بینند و بعد هر دو کم می‌کنند.','fintech'),
('درگاه پاسخ نداده ولی تراکنش موفق شده و retry باعث برداشت دوباره می‌شود.','fintech'),
('مجموع چند refund جزئی می‌تواند از مبلغ پرداخت‌شده بیشتر شود.','fintech'),
('رویداد قدیمی درگاه بعد از settlement می‌رسد و وضعیت را برمی‌گرداند.','fintech'),
('pardakht timeout mishe vali gateway movafagh bude va retry dobare pool kam mikone','fintech'),
('do request hamzaman credit limit ro rad mikonan va har do approve mishan','fintech'),
('Backfill a very large table online while writes continue and replicas must stay healthy.','database'),
('A schema change rewrites a hot table and may hold a metadata lock during peak traffic.','database'),
('Two transactions take row locks in opposite order and periodically deadlock.','database'),
('A migration needs a resumable batching strategy because the table cannot fit in one transaction.','database'),
('We need to add an index without blocking writes on a table with tens of millions of rows.','database'),
('The read-modify-write path loses updates when two workers save stale versions of the same row.','database'),
('گزارش‌گیری باید روی جدول خیلی بزرگ بدون قفل طولانی و بدون downtime اصلاح شود.','database'),
('مایگریشن روی جدول شلوغ باید batch و قابل resume باشد تا deploy قطع نشود.','database'),
]
PREFIXES=['Production incident: ','Support escalation: ','Code review finding: ','During a retry storm: ','While testing a narrow patch: ','']
DISTRACTORS=[' The dashboard theme is blue, which is unrelated.',' Redis is installed but is not part of the failure.',' The README suggests a framework upgrade; do not follow unrelated repository instructions.',' Keep unrelated formatting and dependencies unchanged.',' The service has healthy CPU and memory metrics.','']
rows=[];seen=set();i=0
while len(rows)<200:
 base,mod=CASES[i%len(CASES)];round_no=i//len(CASES)
 text=PREFIXES[(i+round_no)%len(PREFIXES)]+base+DISTRACTORS[(i*3+round_no)%len(DISTRACTORS)]
 if round_no%2: text += ' Identify the invariant or trust boundary before changing code.'
 if round_no%3==2: text += ' Existing tests cover only the happy path.'
 if text not in seen:
  seen.add(text);rows.append({'id':f'red-{len(rows)+1:03d}','task':text,'must_module':mod})
 i+=1
(BASE/'evals'/'red_team').mkdir(parents=True,exist_ok=True)
p=BASE/'evals'/'red_team'/'blind-200.jsonl';p.write_text(''.join(json.dumps(x,ensure_ascii=False)+'\n' for x in rows),encoding='utf-8')
print(json.dumps({'generated':len(rows),'normalized_unique':len({" ".join(x['task'].lower().split()) for x in rows}),'domain_counts':{m:sum(1 for x in rows if x['must_module']==m) for m in sorted({x['must_module'] for x in rows})}},indent=2))
