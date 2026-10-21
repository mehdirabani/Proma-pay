# Incident: Contract Preview Request Storm

## Evidence preserved

| Field | Recorded value |
| --- | --- |
| Incident time | `2026/07/26 02:59:06` (hosting report) |
| Source IP | `5.232.191.xxx` (masked) |
| Affected route | `index.php?route=contracts%2Fpreview` |
| Observed burst | More than 20 distinct GET requests in approximately one second |
| Reported release at incident | Core `1.4.0` |
| Current remediation branch | Core `1.4.8` before the dedicated Preview fix |
| Browser / process ID / active page | Not present in the hosting report; intentionally not inferred |
| Service Worker version at incident | Not present in the hosting report; current worker has no request replay handler |
| Hosting outcome | Source IP was temporarily blocked by shared-host security and later released |

The supplied log contains different financial tuples for the same second, such as
`35,500,000 / 20,000,000 / 6` and `58,000,000 / 48,000,000 / 2`. It does not
contain customer identity data, so no customer data is copied into this record.

## Initial conclusion

The route is an event-driven financial preview route and should never be polled.
The different tuples point to multiple contract forms rather than a retry of one
request. Source review confirmed that the contract list renders the create form
plus one hidden edit form per visible contract, and the previous JavaScript
initialised a preview for every form during page load. That is sufficient to
produce the hosting pattern without assuming malware on the client device.

## Containment

V1.4.8 already introduced a server-side request budget and client fetch spacing.
The dedicated remediation in the next release removes the root cause: hidden
forms no longer schedule Preview requests and Preview is controlled per active
form only.
