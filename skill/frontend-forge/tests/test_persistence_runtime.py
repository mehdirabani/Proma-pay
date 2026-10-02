import unittest,tempfile
from pathlib import Path
from persistence.sqlite_store import SQLiteStore
from runtime_py.runtime_v14 import Runtime
from failure.rollback import rollback_to_latest
from runtime_py.guard_engine import GuardFailure

def good_guard():
    return {"requirement_summary":{"ok":1},"impact_report":{"ok":1},"capability_plan":["frontend"],"risk":"LOW",
            "quality_evidence_complete":True,"quality_pass":True,"no_blocking_regression":True,"learning_skipped_reason":"test"}

class PersistenceRuntimeTests(unittest.TestCase):
    def mk(self):
        td=tempfile.TemporaryDirectory(); store=SQLiteStore(Path(td.name)/"db.sqlite"); rt=Runtime("t",store=store,context=good_guard()); return td,store,rt
    def advance_plan(self,rt):
        for e in ["TaskClassified","ContextResolved","DependenciesAnalyzed","PlanCreated","PlanValidated"]:rt.dispatch(e,{"guard_context":good_guard()})
    def test_persistent_session(self):
        td,s,r=self.mk(); sid=r.session.session_id;r.dispatch("TaskClassified");self.assertEqual(s.get_session(sid)["state"],"TASK_CLASSIFIED");td.cleanup()
    def test_event_persistence(self):
        td,s,r=self.mk();r.dispatch("TaskClassified");self.assertEqual(len(s.list_events(r.session.session_id)),1);td.cleanup()
    def test_checkpoint_and_resume(self):
        td,s,r=self.mk();r.dispatch("TaskClassified");r.checkpoint("c1");r2=Runtime.resume(s,r.session.session_id);self.assertEqual(r2.state,"TASK_CLASSIFIED");td.cleanup()
    def test_rollback(self):
        td,s,r=self.mk();r.dispatch("TaskClassified");r.checkpoint("before");r.dispatch("ContextResolved");x=rollback_to_latest(r);self.assertEqual(x["status"],"ROLLED_BACK");self.assertEqual(r.state,"TASK_CLASSIFIED");td.cleanup()
    def test_guard_enforced_at_plan_validate(self):
        td,s,r=self.mk()
        for e in ["TaskClassified","ContextResolved","DependenciesAnalyzed","PlanCreated"]:r.dispatch(e)
        r.session.context={}
        with self.assertRaises(GuardFailure):r.dispatch("PlanValidated")
        td.cleanup()
    def test_high_risk_requires_approval(self):
        td,s,r=self.mk();self.advance_plan(r);r.session.context["risk"]="HIGH";r.session.context["approval_granted"]=False
        with self.assertRaises(GuardFailure):r.dispatch("ExecutionStarted")
        td.cleanup()
    def test_approval_path(self):
        td,s,r=self.mk()
        # advance to plan-created and validate guards
        for e in ["TaskClassified","ContextResolved","DependenciesAnalyzed","PlanCreated"]:r.dispatch(e)
        ctx=good_guard();ctx.update({"risk":"HIGH","approval_required":True})
        r.session.context=ctx;r.dispatch("PlanValidated",{"guard_context":ctx});r.dispatch("ApprovalRequired",{"guard_context":ctx})
        r.session.context["approval_granted"]=True;r.dispatch("ApprovalGranted",{"guard_context":r.session.context})
        self.assertEqual(r.state,"EXECUTION_STARTED");td.cleanup()
    def test_cancel_path(self):
        td,s,r=self.mk();self.advance_plan(r);r.dispatch("ExecutionStarted",{"guard_context":good_guard()});r.dispatch("CancelRequested");r.dispatch("SafeStop");r.dispatch("CancelFinalized");self.assertEqual(r.state,"TASK_CANCELLED");td.cleanup()
    def test_event_envelope_has_hash(self):
        td,s,r=self.mk();r.dispatch("TaskClassified");e=s.list_event_envelopes(r.session.session_id)[0];self.assertEqual(len(e["payload_hash"]),64);td.cleanup()
    def test_abort_path(self):
        td,s,r=self.mk();r.dispatch("TaskClassified");r.dispatch("ContextFailed");r.dispatch("AbortRequested");r.dispatch("SafeStop");r.dispatch("AbortFinalized");self.assertEqual(r.state,"TASK_ABORTED");td.cleanup()
