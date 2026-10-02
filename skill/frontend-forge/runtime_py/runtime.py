from .io_utils import load_json
from .event_router import resolve_event

class RuntimeSession:
    def __init__(self):
        sm = load_json("runtime/state-machine.json")
        self.state = sm["initial"]
        self.trace = []
        self.activations = []

    def dispatch(self, event, payload=None):
        mapping = resolve_event(event, self.state)
        previous = self.state
        self.state = mapping["to"]
        self.activations.extend(mapping.get("activates", []))
        record = {
            "event": event,
            "from": previous,
            "to": self.state,
            "activates": mapping.get("activates", []),
            "payload": payload or {}
        }
        self.trace.append(record)
        return record

    def run(self, events):
        for item in events:
            if isinstance(item, str):
                self.dispatch(item)
            else:
                self.dispatch(item["event"], item.get("payload"))
        return self.state
