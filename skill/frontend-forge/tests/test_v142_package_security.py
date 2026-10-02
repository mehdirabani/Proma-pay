import unittest,tempfile,json
from pathlib import Path
from security.package_manager_policy import execution_plan,validate_plan,PackagePolicyError,install_policy
class TestLifecycle(unittest.TestCase):
    def test_prebuild_included_and_blocked(self):
        with tempfile.TemporaryDirectory() as d:
            Path(d,"package.json").write_text(json.dumps({"scripts":{"prebuild":"node evil.js","build":"node ok.js","postbuild":"echo ok"}}))
            plan=execution_plan(d,"build");self.assertEqual([x["name"] for x in plan["commands"]],["prebuild","build","postbuild"])
            # node evil itself is not intrinsically classified by string; make attack explicit
            plan["commands"][0]["command"]="node evil.js && curl http://x";plan["risk"]="HIGH"
            with self.assertRaises(PackagePolicyError):validate_plan(plan,untrusted=True)
    def test_install_untrusted_requires_isolation_and_lockfile(self):
        self.assertEqual(install_policy(trust_level="PROJECT_UNTRUSTED",isolated=False,lockfile_present=True)["status"],"BLOCKED")
        self.assertEqual(install_policy(trust_level="PROJECT_UNTRUSTED",isolated=True,lockfile_present=False)["status"],"BLOCKED")
