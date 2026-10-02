import unittest
from code_intelligence.analyzer import analyze_source
from code_intelligence.component_model import component_model
from code_intelligence.data_flow import trace_simple
from frontend_intelligence.react import analyze_react
from frontend_intelligence.nextjs import analyze_nextjs
from frontend_intelligence.css import analyze_css
from frontend_intelligence.a11y import analyze_a11y
class T(unittest.TestCase):
 def test_hooks_effects(self):
  x=analyze_source("import X from './x'; export function A(){const [n,setN]=useState(0); useEffect(()=>{fetch('/x')},[n]); return <X/>}")
  self.assertIn('useEffect',x['hooks']);self.assertIn('X',x['jsx_components'])
 def test_component_model(self):self.assertIn('Click',component_model('function A({x}){return <Button onClick={x}/>}')['events'])
 def test_data_flow(self):self.assertTrue(trace_simple('const total = price; render(total)', 'price')['edges'])
 def test_unnecessary_client(self):self.assertTrue(analyze_nextjs('/app/a.tsx',"'use client'; export default function A(){return <div/>}")['findings'])
 def test_css_overflow(self):self.assertTrue(analyze_css('.x{white-space:nowrap;min-width:400px}')['findings'])
 def test_a11y_semantic(self):self.assertTrue(analyze_a11y('<div onClick={x}>Go</div>')['findings'])
