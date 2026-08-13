# Salary failure investigation

No production request ID or exception log for the salary POST was provided. Local static inspection confirms the form field names match `SalaryService::save()` and the migration defines the referenced columns. The exact production SQL/schema exception is **BLOCKED** without the salary POST Request ID and host log. The redesigned page uses a bounded server-rendered user selector and retains integer-Toman normalization.
