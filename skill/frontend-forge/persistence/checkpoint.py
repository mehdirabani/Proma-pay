from runtime_py.session import Session
def restore_runtime(runtime,checkpoint):
    d=checkpoint["session"]
    runtime.session=Session(**d)
    runtime.trace=checkpoint["trace"]
    runtime.store.update_session(runtime.session.to_dict())
    return runtime
