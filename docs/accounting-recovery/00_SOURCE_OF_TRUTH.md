# Source of truth audit

- Repository: local checkout `C:\Users\pgmme\Desktop\proma-pay`
- Branch: `codex/release-v1.4.5`
- Commit inspected: `ed9ef29`
- Local Core configuration: 1.5.1
- Plugin manifest before this patch: 1.2.12
- Plugin API: 1.0
- Production registry, schema and protected logs: unavailable (BLOCKED)

The local Core does contain a plugin route registry (`PluginManager::registerRoute` and `dispatchRoute`) and closes cursors in `core/Model.php`. The public screenshot cannot be used to infer the deployed registry state. A production 404 is therefore documented as a lifecycle/source-drift hypothesis until registry logs are supplied.
