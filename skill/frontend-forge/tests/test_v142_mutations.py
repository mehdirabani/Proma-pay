import unittest,copy
from runtime_py.io_utils import load_json
from runtime_py.state_graph import validate_graph
from runtime_py.event_router import validate_event_map
class TestMutations(unittest.TestCase):
    def test_remove_failure_repair_transition_detected(self):
        m=load_json("runtime/state-machine.json");m=copy.deepcopy(m);m["transitions"]=[x for x in m["transitions"] if x!=["TEST_FAILURE","REPAIR"] and x!=["TEST_FAILURE","ABORT_REQUESTED"]]
        self.assertFalse(validate_graph(m)["valid"])
