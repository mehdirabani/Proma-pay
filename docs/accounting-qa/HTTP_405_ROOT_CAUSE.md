# HTTP 405 root cause

Bug ID: `ACC-ROUTE-405-001`

## Affected route

- Reported route: `plugin/accounting/ledger/post`
- Intended method: POST
- Intended controller: `Proma\Plugins\Accounting\Controllers\AccountingController@postLedger`
- Form source: `plugins/PromaAccounting/views/ledger.php`

## Reproduction from source

The manifest declared these routes in this order:

1. `GET plugin/accounting/ledger/{userId}`
2. `POST plugin/accounting/ledger/post`

The Core plugin router matched routes in declaration order and immediately returned HTTP 405 when the first matching route had a different method. The dynamic route `ledger/{userId}` also matches the literal route segment `post`.

Result before fix:

- Valid `POST plugin/accounting/ledger/post`: matched `ledger/{userId}` with `userId = post`, saw method mismatch, returned raw 405 and never reached `postLedger`.
- Direct `GET plugin/accounting/ledger/post`: could be treated as the dynamic ledger route instead of the exact POST-only route.
- The 405 response was emitted with `http_response_code(405)` and no Core error body.

## Root cause

Core plugin route dispatch was first-match and method-failing, with no exact-route priority and no method-aware fallback. A broad parameter route could shadow a later exact route.

## Fix

`core/PluginManager.php` now:

- collects all matching plugin routes;
- sorts exact routes before parameterized routes;
- lets an exact route shadow looser parameter routes;
- dispatches the first matching route with the current HTTP method;
- returns Core-rendered HTTP 405 only after all exact matches reject the method;
- sets the `Allow` header from the matched route methods.

## Evidence

- PHP lint for `core/PluginManager.php`: PASSED
- Isolated route dispatch regression:
  - POST `plugin/accounting/ledger/post` selects `postLedger`: PASSED
  - GET `plugin/accounting/ledger/post` returns 405 with `Allow: POST`: PASSED
  - GET `plugin/accounting/ledger/42` still selects the ledger route: PASSED
- Full authenticated browser reproduction: NOT EXECUTED
