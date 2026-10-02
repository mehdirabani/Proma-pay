def validation_plan(task_model,available_tools=None):
    available=set(available_tools or [])
    requested=['syntax','typecheck','lint','tests','build']
    if 'accessibility' in task_model.get('domains',[]):requested.append('accessibility')
    if 'performance' in task_model.get('domains',[]):requested.append('performance')
    return [{'gate':g,'status':'PLANNED' if not available or g in available else 'UNVERIFIED'} for g in requested]
