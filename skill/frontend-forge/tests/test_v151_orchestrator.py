import unittest,tempfile
from pathlib import Path
from engineering_intelligence.orchestrator import analyze_engineering_task
from repository_intelligence.repo_indexer import RepositoryIndexer
class T(unittest.TestCase):
 def test_wrong_target_high_confidence_rejected(self):
  with tempfile.TemporaryDirectory() as d:
   Path(d,'ProductCard.css').write_text('.card{min-width:600px}')
   Path(d,'NotificationCenter.tsx').write_text('export const NotificationCenter=()=> <div>Notifications</div>')
   idx=RepositoryIndexer(d).scan();r=analyze_engineering_task('Fix mobile product card overflow',d,candidate={'path':'NotificationCenter.tsx','confidence':.99},repository_index=idx,revision='r1',task_id='t')
   self.assertNotEqual(r['target_safety']['state'],'TARGET_CONFIRMED');self.assertFalse(r['target_safety']['allow_edit'])
 def test_correct_target_gets_evidence(self):
  with tempfile.TemporaryDirectory() as d:
   Path(d,'ProductCard.css').write_text('.product-card{min-width:600px}')
   Path(d,'ProductCard.tsx').write_text("import './ProductCard.css'; export const ProductCard=()=> <div className='product-card'>Product</div>")
   idx=RepositoryIndexer(d).scan();r=analyze_engineering_task('Fix mobile ProductCard overflow',d,candidate={'path':'ProductCard.css','confidence':.9},repository_index=idx,revision='r1',task_id='t')
   self.assertIn(r['target_safety']['state'],{'TARGET_CONFIRMED','TARGET_PROBABLE'});self.assertTrue(r['root_cause_evidence'])
