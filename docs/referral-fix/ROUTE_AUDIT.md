# Referral route audit

The corrected manifest registers separate routes: administrator `GET plugin/accounting/referrals` (permission `plugin.proma-accounting.referral_admin_view`) and customer `GET portal/referrals`, `GET plugin/accounting/referrals/my` plus POST code creation. The provider registers manifest routes only when the plugin is active; therefore a stale/inactive registry produces the observed core 404. The package uses the legacy-compatible `PromaAccounting/` ZIP root.
