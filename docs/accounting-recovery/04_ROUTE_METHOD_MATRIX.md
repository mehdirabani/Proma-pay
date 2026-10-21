# Route method matrix

`GET plugin/accounting/referrals` — administrator permission `plugin.proma-accounting.referral_admin_view`.

`GET portal/referrals` and `GET plugin/accounting/referrals/my` — authenticated customer self-service.

`GET/POST plugin/accounting/salary` uses the manifest's dedicated GET page and `POST plugin/accounting/salary/save` operation. Wrong methods are handled by the Core plugin dispatcher.
