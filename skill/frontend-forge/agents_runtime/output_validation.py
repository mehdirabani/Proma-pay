import jsonschema
from runtime_py.io_utils import load_json
def validate_agent_output(output):
    jsonschema.validate(output,load_json("contracts/agent-output.schema.json"));return True
