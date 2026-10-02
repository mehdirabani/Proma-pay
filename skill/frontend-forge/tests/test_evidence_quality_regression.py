import unittest,tempfile,json
from pathlib import Path
from evidence.evidence_store import create_evidence,validate_evidence
from runtime_py.quality_v14 import evaluate_quality
from runtime_py.regression_v14 import compare_evidence
import jsonschema
from runtime_py.io_utils import load_json

def make_ev(td,metric,score):
    p=Path(td,f"{metric}.json");p.write_text(json.dumps({"metric":metric,"score":score}))
    return create_evidence(metric,score,"test-tool","1.0",p)

class EvidenceQualityRegressionTests(unittest.TestCase):
    def test_evidence_hash_valid(self):
        with tempfile.TemporaryDirectory() as d:self.assertTrue(validate_evidence(make_ev(d,"performance",90),mode="SIMULATION"))
    def test_evidence_tamper_detected(self):
        with tempfile.TemporaryDirectory() as d:
            e=make_ev(d,"performance",90);Path(e["artifact"]).write_text("tamper");self.assertFalse(validate_evidence(e,mode="SIMULATION"))
    def test_evidence_schema(self):
        with tempfile.TemporaryDirectory() as d:jsonschema.validate(make_ev(d,"performance",90),load_json("contracts/evidence.schema.json"))
    def test_production_numeric_scores_blocked(self):
        r=evaluate_quality({"profile":"web-application","evidence":{"security":95},"mode":"PRODUCTION"})["quality_report"]
        self.assertIn(r["status"],{"UNKNOWN","PARTIAL","BLOCKED"});self.assertNotEqual(r["status"],"PASS")
    def test_missing_accessibility_never_passes(self):
        with tempfile.TemporaryDirectory() as d:
            ev={m:make_ev(d,m,95) for m in ["architecture","code_quality","ux","performance","security","maintainability"]}
            r=evaluate_quality({"profile":"web-application","evidence":ev,"mode":"PRODUCTION"})["quality_report"]
            self.assertEqual(r["status"],"BLOCKED")
    def test_complete_evidence_passes(self):
        with tempfile.TemporaryDirectory() as d:
            ms=["architecture","code_quality","ux","performance","accessibility","security","maintainability"]
            ev={m:make_ev(d,m,95) for m in ms}
            r=evaluate_quality({"profile":"web-application","evidence":ev,"mode":"SIMULATION"})["quality_report"]
            self.assertEqual(r["status"],"PASS")
    def test_simulation_accepts_numeric_but_labeled(self):
        ms=["architecture","code_quality","ux","performance","accessibility","security","maintainability"]
        r=evaluate_quality({"profile":"web-application","evidence":{m:95 for m in ms},"mode":"SIMULATION"})["quality_report"]
        self.assertEqual(r["status"],"PASS")
    def test_regression_pass(self):
        with tempfile.TemporaryDirectory() as d:
            b={m:make_ev(d,"b"+m,95)|{"metric":m} for m in ["performance","accessibility","security"]}
            # Recreate with correct evidence after metric replacement doesn't affect hash validator fields relevant artifact
            c={m:make_ev(d,"c"+m,(95 if m in {"accessibility","security"} else 94))|{"metric":m} for m in ["performance","accessibility","security"]}
            r=compare_evidence({"baseline":b,"current":c,"mode":"SIMULATION"})["regression_report"];self.assertEqual(r["status"],"PASS")
    def test_regression_detects_drop(self):
        with tempfile.TemporaryDirectory() as d:
            b={"performance":make_ev(d,"performance",95)}
            c={"performance":make_ev(d,"performance2",80)}
            r=compare_evidence({"baseline":b,"current":c,"mode":"SIMULATION"})["regression_report"];self.assertEqual(r["status"],"REGRESSION")
