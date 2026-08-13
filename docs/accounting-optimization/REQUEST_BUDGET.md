# Request Budget

Approved target for the optimization release:

- idle Accounting page: zero recurring dynamic requests;
- dashboard initial load: one HTML request plus cached local assets; no periodic refresh;
- search: debounced, one active request, previous request cancelled;
- hidden tab: no optional traffic;
- outbox: no processing in normal page requests; bounded worker batch 1–50;
- no automatic health/reconciliation/full commission scan on page load.

Measured compliance is **NOT EXECUTED** until browser waterfall and staging worker metrics are available. A stable package cannot be approved from static inspection alone.
