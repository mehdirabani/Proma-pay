# Plugin lifecycle

The Core boot path loads routes from the active registry record. A plugin folder alone does not make routes available. Recovery must therefore be: upload nested package, rescan registry, repair stale state, install migrations, activate, then verify route registration. No financial table is deleted by this release.
