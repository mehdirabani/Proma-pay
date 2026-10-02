VALID={'CREATE','MODIFY','DELETE','MOVE','RENAME'}
def validate_operation(op):
    if op.get('op') not in VALID:return {'status':'INVALID_OPERATION'}
    if not op.get('path') and op.get('op') not in {'MOVE','RENAME'}:return {'status':'INVALID_OPERATION'}
    return {'status':'PASS'}
