# Feature flag

No route is conditionally removed by a referral flag in this patch. Routes remain discoverable; feature availability is handled by the page/service so stale settings cannot turn into an unexplained 404. A canonical persisted flag and disabled-state UI require the production settings schema and are NOT EXECUTED locally.
