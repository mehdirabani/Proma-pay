from .io_utils import load_json

def _validator():
    try:
        import jsonschema
        return jsonschema
    except Exception as exc:
        raise RuntimeError("jsonschema is required for schema validation; see requirements.txt") from exc

def validate(instance, schema_rel):
    js = _validator()
    schema = load_json(schema_rel)
    js.validate(instance=instance, schema=schema)
    return True
