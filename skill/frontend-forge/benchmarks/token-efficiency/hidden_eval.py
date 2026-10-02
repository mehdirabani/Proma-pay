# Evaluator-only module. Selector must never import this module.
def evaluate(selected_paths,hidden):
    selected=set(selected_paths);required=set(hidden['relevant_files']);critical=set(hidden.get('critical_files',hidden['relevant_files']))
    tp=len(selected&required);precision=tp/max(1,len(selected));recall=tp/max(1,len(required));critical_recall=len(selected&critical)/max(1,len(critical))
    return {'context_precision':round(precision,4),'context_recall':round(recall,4),'critical_dependency_recall':round(critical_recall,4),'task_success':critical_recall==1.0}
