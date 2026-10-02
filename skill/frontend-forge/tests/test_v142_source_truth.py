import unittest,json
from pathlib import Path
from runtime_py.io_utils import ROOT,load_json
class TestSourceTruth(unittest.TestCase):
    def test_generated_events_match_canonical(self):
        canonical=load_json("runtime/event-transition-map.json")["events"]
        generated=load_json("runtime/events.generated.json")["events"]
        self.assertEqual(generated,[{"name":x["event"],"activates":x.get("activates",[])} for x in canonical])
    def test_manifest_capability_counts(self):
        reg=load_json("runtime/capability-registry.json")["capabilities"];m=load_json("runtime-manifest.json")
        prod=sum(1 for x in reg.values() if x.get("scope")=="production");test=sum(1 for x in reg.values() if x.get("scope")=="test-only")
        self.assertEqual((m["production_capabilities"],m["test_only_capabilities"]),(prod,test))
