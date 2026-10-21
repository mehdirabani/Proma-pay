# Contract Preview Call-Site Inventory

Scope: current source before the dedicated request-storm remediation.

| File / function | Trigger | Runs at load | Hidden form risk | Existing protections | Finding |
| --- | --- | --- | --- | --- | --- |
| `assets/js/app.js` / `initContractForms` | Iterates every `[data-contract-form]` | Yes | Yes | `data-contract-form-bound` avoids duplicate binding | **Root source.** Calls `updatePreview()` at the end for every rendered create and edit form. |
| `assets/js/app.js` / local `updatePreview` | `input`, `change`, and initial call | Yes | Yes | 500ms timer, AbortController, URL-string equality | Does not check visibility, dirty state, or response sequence. Payload uses raw formatted values. |
| `assets/js/app.js` / `hydrateAjaxContent` | AJAX filter result hydration | Indirect | Existing nodes are guarded | Form dataset guard | Does not duplicate listeners on an already bound form, but the initial-preview design remains unsafe for newly rendered hidden forms. |
| `views/contracts/index.php` / create form | One `data-contract-form` | Page render | Usually hidden modal | None at markup level | Must not send Preview until an actual financial edit. |
| `views/contracts/index.php` / edit form loop | One `data-contract-form` per visible contract card | Page render | Yes, all edit modals begin closed | None at markup level | With 12 or 24 cards, page load creates 12 or 24 independent valid payloads. |
| `controllers/ContractsController.php` / `preview` | GET route | No | N/A | Role check, authoritative finance helper | Read-only calculation, but plugin bootstrap previously happened before the route and no endpoint-specific request budget existed. |
| `service-worker.js` | Fetch event | No replay | N/A | Handler returns immediately | No Preview caching, retry, or replay found. |

No other source call site for `contracts/preview`, encoded route variants, `XMLHttpRequest`, or `axios` was found outside ignored release simulation data. Payment preview uses a separate route, `installments/previewPayment`, and is not a caller of this endpoint.

## Required invariants after remediation

1. Contract list initial load sends zero requests to `contracts/preview`.
2. A closed modal and a disconnected form send zero Preview requests.
3. One financial edit produces at most one trailing-edge request for that form.
4. A changed value cancels and supersedes the preceding request.
5. Identical normalized payloads reuse a short-lived per-form response cache.
6. A stale or aborted response cannot mutate a financial preview.
