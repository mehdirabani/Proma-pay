import unittest
from runtime_py.io_utils import load_json
class ContractRegistryTests(unittest.TestCase):
    def test_all_non_agent_declared_outputs_have_contract(self):
        reg=load_json('runtime/capability-registry.json')['capabilities'];contracts=load_json('runtime/output-contracts.json')
        missing=[]
        for cid,c in reg.items():
            if c.get('type')=='agent':continue
            for out in c.get('outputs',[]):
                if out not in contracts:missing.append((cid,out))
        self.assertEqual(missing,[])
