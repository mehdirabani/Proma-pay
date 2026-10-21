# Contract Template Editor Audit — v1.5.10

## Root cause

The template page loaded Quill locally, but deliberately selected `source` as its
initial state. The visible control named «ویرایش ساده» was disabled until a
manual conversion flow was accepted. Consequently an otherwise valid HTML
template appeared as a long raw line in a textarea, exactly as in the reported
screenshot. This was an interaction-design defect, not a corrupt template.

## Corrected design

- Visual editing is the initial state when the already-bundled local Quill asset
  is available; no CDN or per-keystroke request is used.
- HTML is a clearly labelled advanced mode rather than the ordinary workspace.
- The source remains the submitted canonical value, so drafts, published
  versions and existing template history are preserved.
- Template variables are inserted as non-editable visual tokens and serialize
  back to their exact `{{variable_name}}` representation before save.
- The A4 preview is local, sandboxed and manually refreshed. Final preview and
  printing continue to use the canonical server renderer.
- If the visual asset cannot load, the editor falls back to HTML advanced mode
  without discarding source or recovery data.

## Compatibility and safety

Existing plain text and structured HTML templates are rehydrated into the
visual canvas. The server-side allowlist sanitizer remains authoritative at
save/render time; browser cleanup is only defence in depth for the local
preview. Existing draft/publish/restore/audit flows are unchanged.
