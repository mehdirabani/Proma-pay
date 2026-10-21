# Legal workflow UX audit — v1.5.10

The existing case workspace already permits a lawyer assigned to a case to add
attachments and provisional legal costs. `LegalCaseCostService` records new
costs as `pending_approval`; only approved chargeable costs contribute to the
financial summary. Lawyer self-initiation is separately guarded by the active
legal policy and an idempotency key, so an internal review is not mislabelled
as an official filing.

The operational gap is discoverability: the case header has direct «ارسال
ضمیمه» and «ثبت هزینه» controls, while the lawyer queue provides «تشکیل پرونده
داخلی» for eligible contracts. These paths use server-side access checks,
CSRF, audited services and secure uploads; they do not rely on hidden buttons.
