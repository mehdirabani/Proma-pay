from pathlib import Path
from .parsers.typescript_parser import TypeScriptParser
from .parsers.javascript_parser import JavaScriptParser
from .parsers.css_parser import CSSParser
from .parsers.html_parser import HTMLParser
PARSERS={'.ts':TypeScriptParser(),'.tsx':TypeScriptParser(),'.js':JavaScriptParser(),'.jsx':JavaScriptParser(),'.mjs':JavaScriptParser(),'.css':CSSParser(),'.scss':CSSParser(),'.html':HTMLParser()}
def parse_file(path):
    p=Path(path);parser=PARSERS.get(p.suffix.lower())
    if not parser:return {'mode':'UNSUPPORTED','imports':[],'exports':[],'symbols':[]}
    return parser.parse(p.read_text(errors='ignore'),p)
