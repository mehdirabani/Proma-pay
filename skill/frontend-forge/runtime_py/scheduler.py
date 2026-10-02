from concurrent.futures import ThreadPoolExecutor,as_completed
from .io_utils import load_json
from .capability_executor import execute_capability
from security.process_supervisor import PROCESS_REGISTRY

def dependency_levels(selected,deps=None):
    deps=deps or load_json('runtime/capability-dependencies.json');selected=set(selected);done=set();levels=[]
    while done!=selected:
        ready=sorted(x for x in selected-done if set(deps.get(x,[])).issubset(done))
        if not ready:raise ValueError('dependency cycle or missing selected dependency')
        levels.append(ready);done.update(ready)
    return levels

def execute_schedule(selected,contexts,mode='LOCAL',max_workers=4):
    results={}
    for level in dependency_levels(selected):
        with ThreadPoolExecutor(max_workers=min(max_workers,len(level))) as ex:
            futs={ex.submit(execute_capability,c,contexts[c],mode):c for c in level}
            for f in as_completed(futs):results[futs[f]]=f.result()
    return results

def cancel_execution(execution_id):return PROCESS_REGISTRY.cancel(execution_id)
def running_executions():return PROCESS_REGISTRY.running()
