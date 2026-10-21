# Proma Pay V4.0.1 Local QA Results

**Environment:** Windows / Laragon, PHP 8.3.33, MySQL 8.4.3, local application at 127.0.0.1:18777.
**Data safety:** All login sweeps and plugin lifecycle changes were performed against disposable QA copies. The configured application database and original QA fixture were not used for these changes. No payment or legal submission was recorded.

## Authentication and roles

- HTTP login sweep covered all 271 users in the isolated V4 QA copy, using each customer's required four-digit mobile suffix. 267 eligible accounts authenticated and four inactive/non-login accounts were denied as expected.
- Usernames, password hashes, and last-login timestamps for all 271 rows were compared with their pre-test values and restored exactly.
- Admin, technician, operator, and legal-user accounts were also logged in through the local browser. Their dashboards and role-specific pages rendered. The technician panel, orders, expenses, requests, history, and profile routes returned pages instead of HTTP 500.
- Customer credential policy, password-session invalidation, Affiliate lifecycle, repair secret reveal, plugin migration retry, and plugin manifest checks are included in the local static/unit gate.

## Installments and repair

- Follow-up payment regression on a new disposable database cloned from the QA copy: the installment visible in the reported screenshot (including its 9,683,334-Toman payable amount) was submitted through the row's manual-payment dialog, and the next overdue installment was submitted through the header quick-payment modal. Both requests redirected with the success message; each `payment_group` completed with `requested_amount = allocated_amount` and one allocation. The copied QA source and production were not changed.
- `tests/integration_installment_settlement_v150.php` and `tests/integration_payment_overflow_allocation_v204.php` passed against the disposable payment-debug database. Their customer fixtures now use the required four-digit mobile suffix.
- Installments page rendered active, due-today, overdue, partial-payment, and paid tabs. The partial-payment tab loaded its result list and status rows.
- Repair 1.2.3 was updated to 1.2.6 in the isolated QA copy, activated, and passed its health check.
- The repair detail's one-time secret reveal was tested with a synthetic secret on one QA row. The reveal page displayed it, the audit event was written, the next page load no longer exposed it, and the original encrypted QA value was restored and verified.
- Affiliate installation first reproduced the dependency/lifecycle failure. After the Provider registration fix and Accounting update to 1.2.24, Affiliate 1.0.5 installed, activated, passed health check, and opened its admin page.

## Release validation and limits

- PHP lint and all configured static/unit checks passed locally.
- Core ZIP contents, differential update baseline, file hashes, and archive safety checks passed using tools/verify-release.php.
- The destructive database/HTTP integration phase was not run: the release gate is restricted to the existing shared proma_qa_v204 fixture, which was preserved. The separate 271-user login sweep and browser role checks above were run on an isolated QA copy.
- This is a local candidate. Production availability and real payment gateway delivery are not certified.
