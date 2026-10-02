def adversarial_scenarios(task_model):
    domains=set(task_model.get('domains',[]));sc=['empty_state','error_response','long_text']
    if 'responsive' in domains:sc+=['mobile','narrow_container','rtl']
    if 'accessibility' in domains:sc+=['keyboard','focus_order']
    if 'performance' in domains:sc+=['slow_network','large_data']
    return sorted(set(sc))
