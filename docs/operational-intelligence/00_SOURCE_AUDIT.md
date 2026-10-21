# Customer 360 and automation source audit

Date: 2026-10-04  
Repository: `F:\proma-pay`  
Current source version before this change: 2.0.7

## Scope

This audit covers the requested customer workspace and the specific installment, referral, seller commission, and salary triggers in the current request. The broader pasted product prompts describe additional platform modules; those are not represented as completed by this focused change.

## Existing implementation

- `CustomersController::show` was administrator-only and eagerly loaded all contracts, installments, payment logs, identity documents, and customer files into one long page. That made the first response grow with customer history.
- Contracts, installment state, payments, legal cases, identity documents, customer files, operator calls, and calendar events are separate core models. Existing document view/download routes apply access checks.
- `SystemOutbox` supports bounded retryable work and is drained only from CLI or the token-protected cron worker. Notifications are stored in the in-app notification table.
- The repository has PWA/service-worker and Electron code. No native Android project, WebView push bridge, `PushEvent`, `PushManager`, or configured FCM/Web Push provider was found. A remote device push cannot be confirmed from this repository.
- The accounting plugin already listens to `contract.created` and derives seller commissions from its configured sale/commission rules. Its salary migrations define monthly rules and period tables, including an automatic-posting opt-in, but no salary worker was present.
- The affiliate plugin awarded its configured referral reward at customer registration. There was no purchase-completion hook for referral commissions.
- No persisted customer timeline projection or installment milestone scheduler was present. Calendar and outbox workers are token-protected cron actions.

## Implemented data flow

- Customer 360 renders the summary and first overview only. The other tabs load on first selection and keep the response in the page; list views are bounded and paginated.
- The timeline uses a bounded read model assembled from the latest contract, payment, call, legal, and automation records. It does not run a worker or perform writes during page rendering.
- A new daily cron action identifies installment milestones, records each with a unique idempotency key, creates calendar tasks for operator/admin legal review, and enqueues notification work in the existing outbox. The 30-day task is assigned to an administrator because the current legal eligibility route is administrator-only.
- Seller commission continues through the existing contract event and configured accounting rules.
- Affiliate scenario settings now select whether reward qualification happens when the referral customer is created or after that customer's first successful payment. The default for new configurations is the first successful payment.
- Fixed salary is only posted by the scheduled worker when the existing salary rule is active, due, and explicitly has `automatic_posting_enabled = 1`. The period and ledger entry both use unique idempotency keys.

## Operational requirements and limits

- Apply `database/migrations/2026_10_04_customer_360_automation.sql` through the normal updater/migration flow.
- Apply the affiliate plugin migration `2026_10_04_purchase_commissions.sql` when the plugin is active.
- The existing token-protected `cron/calendarreminders` action now also runs a bounded milestone/salary batch. Alternatively, schedule `cron/customerAutomations` daily as a separate worker. Keep `cron/outbox` scheduled separately so notifications are materialized.
- “Push” currently means an in-app notification queued through the outbox. Actual Android/browser remote push needs a configured push provider and a client subscription/bridge, neither of which exists in this checkout.
- The 30-day milestone creates an internal review task. It does not open or refer a formal legal case, calculate a penalty, or create an accounting entry.
- No before/after performance baseline was measured during this source inspection; no performance improvement claim is made.
