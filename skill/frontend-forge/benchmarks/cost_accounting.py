VALID_UNITS={'provider_tokens','tokenizer_tokens','estimated_tokens','bytes','milliseconds','MB'}
class CostLedger:
    def __init__(self):self.rows=[]
    def add(self,domain,name,value,unit,method):
        if unit not in VALID_UNITS:raise ValueError('INVALID_COST_UNIT')
        self.rows.append({'domain':domain,'metric':name,'value':value,'unit':unit,'method':method})
    def totals(self):
        # Never sum across units/domains.
        out={}
        for r in self.rows:out.setdefault((r['domain'],r['unit']),0);out[(r['domain'],r['unit'])]+=r['value']
        return [{'domain':k[0],'unit':k[1],'value':v} for k,v in out.items()]
