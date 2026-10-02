from pathlib import Path
import json, hashlib
class EvidenceParseError(ValueError):pass
class Parser:
    id="base";version="0"
    @property
    def code_hash(self):
        return hashlib.sha256(Path(__file__).read_bytes()).hexdigest()
    def extract(self,metric,path):raise NotImplementedError
class LighthouseParser(Parser):
    id="lighthouse-json";version="2"
    def extract(self,metric,path):
        data=json.loads(Path(path).read_text(encoding="utf-8"))
        key={"performance":"performance","accessibility":"accessibility","seo":"seo"}.get(metric)
        try:score=float(data["categories"][key]["score"])*100
        except Exception as e:raise EvidenceParseError("invalid lighthouse report") from e
        return score,{"category":key}
class ReviewParser(Parser):
    id="validated-review-json";version="2"
    def extract(self,metric,path):
        data=json.loads(Path(path).read_text())
        if data.get("metric")!=metric or "score" not in data or not data.get("validated"):
            raise EvidenceParseError("invalid validated review")
        return float(data["score"]),{"validated":True}
PARSERS={"lighthouse":LighthouseParser(),"review-agent":ReviewParser()}
def get_parser(source):
    if source not in PARSERS:raise EvidenceParseError(f"no trusted parser for {source}")
    return PARSERS[source]
