# Legal cost approval audit — v1.5.10

Legal costs are synchronized from a legal action into `legal_case_costs`.
Their lifecycle begins with `pending_approval`; `LegalCaseCostService::approve`
is transactional and audit-logged. Only `approved`, outstanding, chargeable
costs are included by `ContractLegalCostSummaryService`; rejected/reversed and
pending values are not silently added to customer debt.

The contract legal tab shows the approval state and provides the management
approval/reversal action. A full cross-contract approval queue remains a later
workflow enhancement and is not represented as completed by this release.
