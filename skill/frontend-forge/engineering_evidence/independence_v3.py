def independence(a,b):
 if not a or not b:return 'UNKNOWN'
 if a.get('observation_root_id')==b.get('observation_root_id'):return 'CORRELATED'
 if a.get('collector_id')==b.get('collector_id'):return 'PARTIALLY_CORRELATED'
 if a.get('source_id')==b.get('source_id'):return 'CORRELATED'
 return 'INDEPENDENT'
