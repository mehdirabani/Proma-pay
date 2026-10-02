from persistence.checkpoint import restore_runtime
def rollback_to_latest(runtime):
    cp=runtime.store.latest_checkpoint(runtime.session.session_id)
    if not cp:return {"status":"NO_CHECKPOINT"}
    restore_runtime(runtime,cp)
    return {"status":"ROLLED_BACK","checkpoint":cp["id"],"state":runtime.state}
