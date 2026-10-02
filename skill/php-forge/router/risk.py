#!/usr/bin/env python3
from .common import REG,level_num

def classify_risk(concepts,scope,repository=None):
    risk='R2'; reasons=[]
    for cid,hit in concepts.items():
        c=REG['concepts'][cid]; floor=c.get('risk_floor','R2')
        if level_num(floor)>level_num(risk): risk=floor
        if level_num(floor)>=4: reasons.append(cid)
    textual=scope['name'] in REG['risk_policy']['textual_scopes']
    # textual scope can de-escalate non-safety financial vocabulary, never explicit critical security concepts.
    critical=any(REG['concepts'][cid].get('safety_critical') and REG['concepts'][cid].get('domain')=='security' for cid in concepts)
    if textual and not critical:
        risk='R1'; reasons=['textual/UI scope without safety-critical concept']
    return {'level':risk,'reasons':reasons or ['ordinary engineering change']}
