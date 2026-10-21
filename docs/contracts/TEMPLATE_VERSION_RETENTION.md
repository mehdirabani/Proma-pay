# Template Version Retention

Referenced and finalized legal template versions remain in the database. A non-current version may be archived with `contract_templates.archive`; archived versions are hidden from the normal list and can be displayed explicitly.

Restoring an archived version always creates a new draft. It never reactivates the archived row. Publishing another draft is required before the old current version can be archived. Purging referenced legal versions is intentionally not exposed through the normal UI.

