from .io_utils import load_json
from .event_router import resolve_event
from .guard_engine import evaluate_transition_guards,GuardFailure
from .session import Session,now

class Runtime:
    def __init__(self, task_id="task", mode="LOCAL", store=None, context=None):
        self.machine=load_json("runtime/state-machine.json")
        self.session=Session(task_id=task_id,mode=mode,context=context or {})
        self.trace=[]
        self.store=store
        if store: store.create_session(self.session.to_dict())

    @property
    def state(self): return self.session.state

    def dispatch(self,event,payload=None):
        payload=payload or {}
        mapping=resolve_event(event,self.state)
        ctx=dict(self.session.context); ctx.update(payload.get("guard_context",{}))
        evaluate_transition_guards(mapping["from"],mapping["to"],ctx)
        before=self.state
        self.session.state=mapping["to"]; self.session.updated_at=now()
        for cap in mapping.get("activates",[]):
            if cap not in self.session.active_capabilities: self.session.active_capabilities.append(cap)
        rec={"event":event,"from":before,"to":self.state,"activates":mapping.get("activates",[]),"payload":payload}
        self.trace.append(rec)
        if self.store:
            self.store.append_event(self.session.session_id,rec)
            self.store.update_session(self.session.to_dict())
        return rec

    def checkpoint(self,label):
        if not self.store: raise RuntimeError("persistence store required")
        return self.store.save_checkpoint(self.session.session_id,label,self.session.to_dict(),self.trace)

    @classmethod
    def resume(cls,store,session_id):
        store.verify_resume(session_id)
        data=store.get_session(session_id)
        if not data: raise KeyError(session_id)
        obj=cls.__new__(cls)
        obj.machine=load_json("runtime/state-machine.json")
        obj.session=Session(task_id=data["task_id"],session_id=data["session_id"],state=data["state"],
            created_at=data["created_at"],updated_at=data["updated_at"],attempt=data["attempt"],
            mode=data["mode"],active_capabilities=data["active_capabilities"],
            artifacts=data["artifacts"],failures=data["failures"],context=data["context"])
        obj.trace=store.list_events(session_id); obj.store=store
        return obj
