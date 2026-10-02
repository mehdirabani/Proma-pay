import unittest,jsonschema
from runtime_py.registry_validation import validate_registry
from runtime_py.io_utils import load_json
class RegistrySchemaTests(unittest.TestCase):
    def test_registry_resolves(self):self.assertTrue(validate_registry()["valid"],validate_registry()["errors"])
    def test_registry_schema(self):jsonschema.validate(load_json("runtime/capability-registry.json"),load_json("contracts/capability.schema.json"))
