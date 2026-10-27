# Portfolio Risk Analytics — v7.0.4

## Source audit

- Local application version: `7.0.4` (`config/version.php`).
- Branch at implementation start: `codex/v704-lawyer-settlement`.
- Commit at implementation start: `716d4582a06b57b14aaca91de3b93fa25466b2b8`.
- The working tree was already dirty at task start. Those pre-existing changes were preserved; no commit, push, deployment, or production data operation was performed.
- Existing sources reused: `InstallmentFinancialStateService`, `CreditRiskEngine`, `payments`, `payment_corrections`, `installments`, `contracts`, `legal_cases`, and `SpreadsheetHelper`.
- No migration or new financial source-of-truth table was needed.

### Confirmed gaps and fixes

| Area | Prior behavior / risk | Implementation |
|---|---|---|
| Admin collection rate | Period cash could be divided by unrelated same-month due obligations. | Replaced with same-due-cohort principal collected / scheduled principal; null when the denominator is zero or any cohort installment cannot be calculated. |
| Cash trend | Paid rows could include corrected or non-installment events. | Only paid, uncorrected installment receipts with no correction record are included. |
| Balances and overdue | Raw stored amounts can diverge from financial allocation rules. | Reused canonical installment-state calculations and integer Toman values. |
| Risk list | Priority order could be mistaken for Proma Score. | Labels it as follow-up priority and displays cached score separately; stale, missing, or rule-mismatched snapshots are unavailable. |
| Risk summary freshness | Per-customer recalculation could write snapshots during a report. | Reads bounded batches of fresh snapshots only; report paths do not call `CreditRiskEngine::profile()`. |
| Historical schedules | Old schedule versions cannot reliably be reconstructed from current rows. | Past cohorts use the currently stored schedule and disclose that limitation. |
| Drill-down | Canceled installments could appear in source detail despite exclusion from report totals; cash page contribution was missing. | Shared installment filters exclude canceled rows; cash detail reports a page sum. Month-to-date summaries are calculated from source dates, not weekly bucket labels. |

## Metric dictionary

All amounts are integer Toman. Date filters use Gregorian database dates; the UI formats them in Jalali. Unless stated otherwise, contract filters, customer search, operator, risk snapshot, and contract status filters are applied to the same source rows in report, detail, and export.

| Metric | Business definition and formula | Source/date/status rules | Drill-down |
|---|---|---|---|
| Scheduled due principal | Sum of `installments.base_amount` due within selected range; canceled installments and canceled contracts excluded. | Due date is inclusive. Gross contractual installment principal is used, even if the installment has already been paid. | `risk/portfolioDetail?metric=due` |
| Actual installment receipts | Sum of `payments.amount` by payment date. | Requires `status=paid`, `is_corrected=0`, installment payment type, and no `payment_corrections` record. Down-payment and legal-cost rows are excluded. Grouped receipts are represented by their individual payment allocation rows; `payment_groups.allocated_amount` is not added. | `risk/portfolioDetail?metric=cash` |
| Due-cohort collection rate | For installments whose due date is in the selected range and no later than as-of: `Σ min(base principal, canonical effective paid principal) / Σ base principal × 100`. | Early payments for future installments do not enter this cohort. The rate is unavailable if the denominator is zero or a cohort financial state is incomplete. | `risk/portfolioDetail?metric=cohort` |
| Outstanding installment principal | Sum of canonical `remaining_principal` across non-canceled installments and contracts. | Uses `InstallmentFinancialStateService`; down-payments and penalties are not principal balance. Calculation failures do not become zero balances silently and are counted for review. | `risk/portfolioDetail?metric=balance` |
| Overdue principal and penalties | For due dates before as-of with positive principal or penalty: sum canonical remaining principal; penalties are reported separately and included in aging total. | Ages are 1–7, 8–30, 31–60, 61–90, and 90+ days; contract counts are distinct within each bucket. | `risk/portfolioDetail?metric=aging` |
| Scheduled cash inflow | Sum canonical remaining principal of future, non-canceled installments within the selected horizon on open contracts. | Contractual schedule only; no probability or expected-collection model. | `risk/portfolioDetail?metric=forecast` |
| Risk priority | Overdue contracts ordered by overdue principal, maximum delay, then contract ID; existing risk level/score shown separately. | Saved snapshot must be within six hours and use the current `CreditRiskEngine` rules fingerprint. Missing/stale is `unavailable`. | Portfolio risk table and customer profile link |

## Architecture and implementation

- `models/PortfolioRiskAnalyticsService.php` performs a read-only MySQL transaction, keyset-scans installments in batches of 250, reuses canonical financial-state calculations, builds trend/aging/forecast aggregates, and pages source rows.
- `controllers/RiskController.php` exposes admin-only report, drill-down, and Excel endpoints. Invalid metric names are rejected.
- `controllers/DashboardController.php` now uses canonical collection, cohort, balance, overdue, and forecast KPIs.
- `views/risk/portfolio.php` and `views/risk/portfolio-detail.php` provide the RTL report, filter forms, accessible data tables, clickable charts, page totals, and coverage notes. `assets/css/pages/portfolio-analytics.css` styles the page.
- `helpers/CreditRiskEngine.php` exposes the existing rules fingerprint and shared six-hour freshness constant; report reads do not recalculate customer profiles.
- Excel output uses `SpreadsheetHelper::downloadPaginatedXlsx()` and exports source IDs, metric version/name, filters, period/as-of/extraction timestamps, status, source scope, contribution, principal, penalty, and coverage.
- No new accounting engine, financial correction path, forecast probability model, or migration was introduced.

## QA reconciliation evidence

`tests/integration_portfolio_risk_analytics_v704.php` creates isolated QA records on a dedicated temporary v7.0.4 MySQL schema. This is fixture-based QA evidence, not production portfolio data. It checks independent source-row totals and report/detail agreement for a normal installment, an early future payment, a completed multi-installment group, corrected/reversed/pending payments, down-payment, legal-cost receipt, canceled installment, partial principal, two aging buckets, missing risk data, current-month week-boundary totals, and no report writes.

Fixture reconciliation (Toman):

| Metric | Report | Independent source calculation | Result |
|---|---:|---:|---|
| Actual installment receipts | 50,000 | 30,000 valid single receipt + 10,000 early receipt + 2 × 5,000 grouped allocation; corrected, reversed, pending, down-payment and legal-cost rows excluded | PASS |
| Due-cohort principal collected | 25,000 | Canonical principal allocation for the installment due today; future prepayments excluded | PASS |
| Due-cohort obligation | 100,000 (112,000 when run on the first day of a month with the boundary fixture also due that day) | Base principal for the same due-date cohort | PASS |
| 30-day scheduled inflow | 50,000 | 35,000 remaining on first future installment + 15,000 on second; canceled 80,000 installment excluded | PASS |
| Outstanding installment principal | 267,000 | 75,000 current + 35,000 future + 60,000 and 70,000 overdue + 12,000 month-boundary + 15,000 second future | PASS |
| Overdue principal | 142,000 for the current test date | 60,000 + 70,000 overdue plus the 12,000 month-boundary installment while it is overdue | PASS |

## Verification status

Executed on the isolated test database `proma_qa_portfolio_b3ee3c67cb` (MySQL 8.4.3, port 13319):

- `tests/static_portfolio_risk_analytics_v704.php` — PASS.
- `tests/integration_portfolio_risk_analytics_v704.php` — PASS after adding grouped allocations, reversed-payment exclusion, canceled-detail exclusion, and week/month-boundary reconciliation.
- PHP lint for the portfolio service, risk controller, portfolio views, and integration test — PASS.

The integration test exercises source totals and no-write behavior, but it does not constitute the complete T01–T30 matrix. HTTP role enforcement, large-scale performance, browser/mobile behavior, stale-snapshot fixtures, an incomplete historical-state fixture, export download/XLSX opening, and post-snapshot mutation are not integration-tested here.

## Limitations and risks

- Historical cohorts are measured against the current installment schedule. No prior schedule version is synthesized; legacy amendments may therefore change historical obligation totals.
- Forecast is scheduled principal only. It does not predict actual collection likelihood, penalties, or net business cash flow.
- Risk freshness requires the existing snapshot and current score fingerprint; unavailable data is not recalculated on report reads.
- Source scanning is chunked and drill-down is server-paginated, but report aggregates are still computed over the filtered installment set on each request. Large-production-scale latency has not been benchmarked.
- Excel export uses a 100,000-row helper limit and requires the PHP ZIP extension at runtime. The local helper smoke produced a valid OOXML ZIP workbook and verified a text and numeric cell; the authenticated controller download and filter-to-export reconciliation were not exercised end-to-end.
- Full RBAC and responsive browser QA, and acceptance cases T12, T17, T19, T21–T25, T27–T30, remain unverified or only statically covered.
