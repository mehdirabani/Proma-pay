# Contract Preview Request Storm - Root Cause

## Answer

One contract-list page generated Preview requests for many unrelated principal
amounts because it rendered a create form and a hidden edit form for every
visible contract, while `initContractForms()` unconditionally called
`updatePreview()` for every one of those forms on `DOMContentLoaded`.

Each valid edit form had its own existing principal, down payment, month count,
rate and interest type. The 500ms debounce timers therefore expired together
and emitted one GET request per hidden edit modal. A card page that previously
showed 24 contracts could emit more than 20 different Preview payloads in the
same second. This matches the hosting log exactly.

## Source evidence

- `views/contracts/index.php` contains the create form and a `foreach` loop
  rendering an edit modal with `data-contract-form` and
  `data-preview-url="contracts/preview"` for each contract.
- `assets/js/app.js` iterates every `[data-contract-form]`, including closed
  modals, and ends initialisation with `updatePreview()`.
- The previous `updatePreview()` starts a 500ms timer when the existing
  financial values are valid. It did not test whether the form was connected,
  visible, open, or changed by the user.
- Existing per-form binding prevents repeated listeners on the same DOM node;
  the principal defect was not a duplicate listener but an unsafe initial
  request across many distinct forms.

## Secondary contributors

- The prior payload key used formatted raw field strings rather than normalized
  finance values.
- There was no sequence check before applying responses.
- The preview route booted optional plugins before controller dispatch, adding
  unnecessary work to every burst request.

## Excluded causes

- The current service worker does not cache, replay, or retry Preview requests.
- No other `contracts/preview` caller, XMLHttpRequest call, or axios call exists
  in the active runtime source.
