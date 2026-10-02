import unittest
from token_intelligence.symbol_slice import extract_symbols,structure_index
from token_intelligence.project_graph import ProjectGraph
class T(unittest.TestCase):
 def test_symbols(self):self.assertEqual(extract_symbols('export function a(){}\ninterface B {}'),['B','a'])
 def test_graph_neighborhood(self):
  g=ProjectGraph();g.edge('ProductCard','ProductGrid');g.edge('ProductCard','css');g.edge('Other','Far');self.assertEqual(g.neighborhood('ProductCard',1),{'ProductCard','ProductGrid','css'})
