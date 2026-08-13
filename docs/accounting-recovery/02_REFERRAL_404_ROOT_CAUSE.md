# Referral 404 investigation

Known IDs `ce182aed3add5d549d8c56b0`, `976d028790104998b850a7b6` and `a2359cb2527a6015d6588145` are not present in locally available protected logs. Locally, `plugin/accounting/referrals` is declared in `plugin.json` and registered by `AccountingServiceProvider` only when the registry status is active. A deployed stale/inactive registry or failed activation is the only confirmed local explanation for route disappearance; the exact production state remains **BLOCKED**.
