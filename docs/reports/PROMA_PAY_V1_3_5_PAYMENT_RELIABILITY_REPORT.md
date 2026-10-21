# Proma Pay V1.3.5 Payment Reliability Report

## Scope

- Manual installment payment
- Gateway completion
- Plugin hook isolation
- Notification isolation
- Duplicate submission protection

## Expected behavior

1. Core financial write commits first.
2. Side effects are enqueued after the financial write.
3. Notification or plugin errors do not change the committed payment.
4. Duplicate resubmission with the same request UUID returns the original result.

## Files changed

- `models/Payment.php`
- `controllers/InstallmentsController.php`
- `helpers/PaymentGroupService.php`
- `controllers/PaymentsController.php`
- `models/SystemOutbox.php`
- `models/PaymentRequest.php`
- `database/migrations/2026_07_13_payment_reliability_v135.sql`
