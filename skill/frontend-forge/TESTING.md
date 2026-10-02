# Test Infrastructure — v13.1

Run:

```bash
python tests/run_tests.py
```

The suite executes, rather than only inspects:
- state graph validation
- event ↔ transition validation
- capability registry resolution
- router scenarios
- failure injection scenarios
- JSON Schema validation
- quality profile/measurement evaluation
- regression runner
- happy-path runtime integration
- repair-path runtime integration
- abort-path runtime integration

`tests/TEST_RESULTS.json` is generated from an actual run during package build.
