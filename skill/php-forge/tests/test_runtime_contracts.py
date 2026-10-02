#!/usr/bin/env python3
from pathlib import Path
import tempfile,json,sys
BASE=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(BASE))
from router.engine import route
from scripts.repo_intelligence_v3 import inspect

def assert_(x,msg):
    if not x: raise AssertionError(msg)
with tempfile.TemporaryDirectory() as td:
    root=Path(td);(root/'composer.json').write_text('{"require":{"php":"^8.2"}}')
    p=root/'packages/payments';p.mkdir(parents=True)
    (p/'composer.json').write_text('{"require":{"php":"^8.3","symfony/framework-bundle":"^7.0"}}')
    (p/'composer.lock').write_text(json.dumps({'packages':[{'name':'symfony/framework-bundle','version':'v7.2.1'}]}))
    (p/'bin').mkdir();(p/'bin/console').write_text('')
    (p/'src').mkdir();(p/'src/X.php').write_text('<?php')
    info=inspect(root,'packages/payments/src/X.php')
    assert_(info['is_monorepo'],'monorepo not detected')
    assert_(Path(info['package_root'])==p.resolve(),'nearest package boundary not selected')
    sf=[x for x in info['frameworks'] if x['name']=='symfony']
    assert_(sf and sf[0]['version']=='v7.2.1' and sf[0]['confidence_level']=='confirmed','lock version evidence failed')
    assert_(info['secrets_loaded'] is False,'secret policy failed')
    conflict=route('This is Laravel. Add a controller.',info)
    assert_(conflict.get('conflicts'),'framework conflict not reported')
# scope/context gates
ui=route('Rename the payment settings label only; do not alter payment behavior.')
assert_('fintech' not in {x['id'] for x in ui['modules']},'UI-only over-routed fintech')
assert_(ui['risk']['level']=='R1','UI-only risk not de-escalated')
high=route('Two concurrent withdrawals can both spend the same available balance.')
mods={x['id'] for x in high['modules']}
assert_('fintech' in mods and 'testing' in mods,'financial invariant route failed')
assert_(high['autonomy']['max']=='A2','high-risk autonomy gate failed')
print('PASS runtime contracts')
