from pathlib import Path
import json,hashlib
from tokenizers.estimated_tokenizer import EstimatedTokenizer
from routing.intent_normalizer import normalize_intent
ROOT=Path(__file__).resolve().parents[1]
def _registry():return json.loads((ROOT/'routing/module-registry.json').read_text())
def module_cost(name,tokenizer=None):
    reg=_registry();m=reg[name];text=''
    for rel in m.get('files',[]):
        p=ROOT/rel
        if p.exists():text+=p.read_text(errors='ignore')+'\n'
    tok=tokenizer or EstimatedTokenizer();c=tok.count(text)
    return {'tokens':c.tokens,'measurement_state':c.state,'method':c.method,'module_hash':hashlib.sha256(text.encode()).hexdigest()}
def select_modules(task,risk='LOW'):
    reg=_registry();intent=normalize_intent(task);loaded=[];rejected=[];reason={}
    wanted={'core-router'}
    for d in intent['domains']:
        cfg=json.loads((ROOT/'routing/intent-registry.json').read_text()).get(d,{})
        wanted.update(cfg.get('modules',[]))
    if risk in {'HIGH','CRITICAL'}:wanted.add('security')
    for name in reg:
        if name in wanted:loaded.append(name);reason[name]='intent/risk/always'
        else:rejected.append(name);reason[name]='no normalized intent signal'
    costs={x:module_cost(x) for x in loaded}
    return {'loaded_modules':loaded,'rejected_modules':rejected,'reason':reason,'intent':intent,'module_costs':costs,'estimated_token_cost':sum(x['tokens'] for x in costs.values())}
