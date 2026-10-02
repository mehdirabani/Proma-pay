import unittest,copy
from runtime_py.state_graph import validate_graph
from runtime_py.event_router import validate_event_map
from runtime_py.guard_engine import evaluate_guard,evaluate_transition_guards,GuardFailure
from runtime_py.io_utils import load_json

class StateEventGuardTests(unittest.TestCase):
    def test_graph_valid(self): self.assertTrue(validate_graph()["valid"])
    def test_all_states_reachable(self): self.assertEqual(validate_graph()["unreachable"],[])
    def test_event_map_valid(self): self.assertTrue(validate_event_map()["valid"])
    def test_event_map_mutation_unknown_capability(self):
        data=copy.deepcopy(load_json("runtime/event-transition-map.json"))
        data["events"][0]["activates"].append("NO_SUCH_CAP")
        self.assertFalse(validate_event_map(event_data=data)["valid"])
    def test_state_mutation_missing_repair_path(self):
        m=copy.deepcopy(load_json("runtime/state-machine.json"))
        m["transitions"]=[t for t in m["transitions"] if t!=["TEST_FAILURE","REPAIR"] and t!=["TEST_FAILURE","ABORT_REQUESTED"]]
        self.assertFalse(validate_graph(m)["valid"])
    def test_guard_pass_evidence(self): self.assertTrue(evaluate_guard("requirements_mapped",{"requirement_summary":{"x":1}}))
    def test_guard_fail_evidence(self):
        with self.assertRaises(GuardFailure): evaluate_guard("requirements_mapped",{})
    def test_guard_risk_pass_low(self): self.assertTrue(evaluate_guard("risk_policy_satisfied",{"risk":"LOW"}))
    def test_guard_risk_requires_approval(self):
        with self.assertRaises(GuardFailure): evaluate_guard("risk_policy_satisfied",{"risk":"HIGH"})
    def test_guard_risk_high_with_approval(self): self.assertTrue(evaluate_guard("risk_policy_satisfied",{"risk":"HIGH","approval_granted":True}))
