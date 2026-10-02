import unittest,tempfile,copy
from pathlib import Path
from runtime_py.runtime_v14 import Runtime
from persistence.sqlite_store import SQLiteStore
from runtime_py.guard_engine import GuardFailure
from runtime_py.event_router import validate_event_map
from runtime_py.io_utils import load_json
from browser.browser_runtime import BrowserRuntime

GOOD={"requirement_summary":{"ok":1},"impact_report":{"ok":1},"capability_plan":["frontend"],"risk":"LOW",
"quality_evidence_complete":True,"quality_pass":True,"no_blocking_regression":True,"learning_skipped_reason":"test"}

class IntegrationMutationTests(unittest.TestCase):
    def test_happy_path(self):
        with tempfile.TemporaryDirectory() as d:
            s=SQLiteStore(Path(d,"db"));r=Runtime("t",store=s,context=dict(GOOD))
            for e in ["TaskClassified","ContextResolved","DependenciesAnalyzed","PlanCreated","PlanValidated","ExecutionStarted","OutputGenerated","QualityValidationStarted","QualityGatePassed","KnowledgeUpdated"]:
                r.dispatch(e,{"guard_context":r.session.context})
            self.assertEqual(r.state,"TASK_COMPLETED")
    def test_quality_guard_failure(self):
        with tempfile.TemporaryDirectory() as d:
            s=SQLiteStore(Path(d,"db"));ctx=dict(GOOD);r=Runtime("t",store=s,context=ctx)
            for e in ["TaskClassified","ContextResolved","DependenciesAnalyzed","PlanCreated","PlanValidated","ExecutionStarted","OutputGenerated","QualityValidationStarted"]:r.dispatch(e,{"guard_context":ctx})
            r.session.context["quality_evidence_complete"]=False
            with self.assertRaises(GuardFailure):r.dispatch("QualityGatePassed",{"guard_context":r.session.context})
    def test_resume_after_simulated_crash(self):
        with tempfile.TemporaryDirectory() as d:
            s=SQLiteStore(Path(d,"db"));r=Runtime("t",store=s,context=dict(GOOD));r.dispatch("TaskClassified");r.checkpoint("cp")
            sid=r.session.session_id;del r
            r2=Runtime.resume(s,sid);self.assertEqual(r2.state,"TASK_CLASSIFIED")
    def test_corrupt_event_map_detected(self):
        data=copy.deepcopy(load_json("runtime/event-transition-map.json"));data["events"][0]["to"]="NOPE"
        self.assertFalse(validate_event_map(event_data=data)["valid"])

    def test_agent_conflict_recovery(self):
        with tempfile.TemporaryDirectory() as d:
            s=SQLiteStore(Path(d,"db"));r=Runtime("t",store=s,context=dict(GOOD))
            for e in ["TaskClassified","ContextResolved","DependenciesAnalyzed","PlanCreated"]:r.dispatch(e)
            r.dispatch("AgentConflictDetected")
            self.assertEqual(r.state,"AGENT_CONFLICT")
            r.dispatch("AgentConflictResolved",{"guard_context":GOOD})
            self.assertEqual(r.state,"PLAN_VALIDATED")

    def test_browser_pass_or_unavailable(self):
        with tempfile.TemporaryDirectory() as d:
            b=BrowserRuntime(d);a=b.availability();self.assertIn(a["status"],{"AVAILABLE","TOOL_UNAVAILABLE"})
