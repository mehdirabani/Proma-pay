import math
LABEL_VALUE={'EXACT':1.0,'ACCEPTABLE':0.8,'NOT_FOUND_CORRECTLY':1.0,'AMBIGUOUS':0.45,'WRONG':0.0}
class OpenSetConfidenceModel:
    def __init__(self,thresholds=None):
        self.thresholds=thresholds or {'resolve':0.9,'expand':0.55,'abstain':0.0};self.bins=[];self.not_found_score=.12
    def feature_score(self,*,top_score,margin,evidence_sources,graph_consistency,parser_confidence,intent_certainty,ambiguity,negative_penalty):
        raw=(.31*top_score+.22*margin+.08*min(evidence_sources,4)/4+.10*graph_consistency+.10*parser_confidence+.09*intent_certainty-.16*ambiguity-.14*negative_penalty)
        return max(0,min(1,raw))
    def fit(self,records,bins=12):
        rows=sorted(((float(r['score']),float(LABEL_VALUE.get(r['label'],0)),r) for r in records), key=lambda x:(x[0],x[1]))
        if not rows:return self
        chunk=max(1,len(rows)//bins);out=[]
        for i in range(0,len(rows),chunk):
            c=rows[i:i+chunk];out.append({'lo':c[0][0],'hi':c[-1][0],'p':sum(y for _,y,_ in c)/len(c),'n':len(c)})
        # isotonic-like monotonic pooling (simple PAVA)
        blocks=[{'lo':b['lo'],'hi':b['hi'],'p':b['p'],'n':b['n']} for b in out]
        i=0
        while i<len(blocks)-1:
            if blocks[i]['p']>blocks[i+1]['p']:
                a,b=blocks[i],blocks[i+1];n=a['n']+b['n'];m={'lo':a['lo'],'hi':b['hi'],'p':(a['p']*a['n']+b['p']*b['n'])/n,'n':n};blocks[i:i+2]=[m];i=max(0,i-1)
            else:i+=1
        self.bins=blocks
        # Calibrated selective threshold: cost of a wrong auto-resolution is 4x abstention.
        scored=[(self.calibrate(s),y,r) for s,y,r in rows]
        candidates=sorted({p for p,_,_ in scored})
        best=(10,None)
        for th in candidates:
            auto=[(p,y,r) for p,y,r in scored if p>=th and r.get('target_present',True)]
            if not auto:continue
            wrong=sum(1 for _,y,_ in auto if y<.8);coverage=len(auto)/max(1,sum(1 for _,_,r in scored if r.get('target_present',True)))
            cost=4*wrong/len(auto)+(1-coverage)*.25
            if cost<best[0]:best=(cost,th)
        resolve=best[1] if best[1] is not None else .9
        self.thresholds={'resolve':max(.55,min(.99,resolve)),'expand':max(.3,min(.85,resolve-.2)),'abstain':0.0}
        # Open-set threshold is learned from retrieval-score separation between present and absent calibration tasks.
        present=[float(r.get('retrieval_score',0)) for _,_,r in rows if r.get('target_present')]
        absent=[float(r.get('retrieval_score',0)) for _,_,r in rows if not r.get('target_present')]
        if present and absent:
            cand=sorted(set(present+absent));best_acc=-1;best_th=.12
            for th in cand:
                ok=sum(x>=th for x in present)+sum(x<th for x in absent);acc=ok/(len(present)+len(absent))
                if acc>best_acc:best_acc,best_th=acc,th
            self.not_found_score=best_th
        return self
    def calibrate(self,score):
        if not self.bins:return max(0,min(1,float(score)))
        b=min(self.bins,key=lambda x:0 if x['lo']<=score<=x['hi'] else min(abs(score-x['lo']),abs(score-x['hi'])))
        return max(0,min(1,b['p']))
    def status(self,p,ambiguity=False):
        if ambiguity and p<self.thresholds['resolve']+.04:return 'TARGET_AMBIGUOUS'
        if p>=self.thresholds['resolve']:return 'TARGET_RESOLVED'
        if p>=self.thresholds['expand']:return 'CONTEXT_EXPANSION_REQUIRED'
        return 'TARGET_LOW_CONFIDENCE'
    def evaluate(self,records):
        vals=[(self.calibrate(float(r['score'])),float(LABEL_VALUE.get(r['label'],0))) for r in records]
        if not vals:return {'count':0}
        brier=sum((p-y)**2 for p,y in vals)/len(vals);mae=sum(abs(p-y) for p,y in vals)/len(vals)
        buckets={}
        for p,y in vals:buckets.setdefault(min(9,int(p*10)),[]).append((p,y))
        ece=sum(len(v)/len(vals)*abs(sum(p for p,_ in v)/len(v)-sum(y for _,y in v)/len(v)) for v in buckets.values())
        high=[(p,y) for p,y in vals if p>=self.thresholds['resolve']];high_wrong=sum(1 for _,y in high if y<.8)/max(1,len(high))
        return {'mae':round(mae,4),'brier':round(brier,4),'ece':round(ece,4),'count':len(vals),'high_confidence_wrong_rate':round(high_wrong,4),'thresholds':self.thresholds,'not_found_score':round(self.not_found_score,4),'buckets':[{'bucket':k,'n':len(v),'mean_confidence':round(sum(p for p,_ in v)/len(v),4),'accuracy':round(sum(y for _,y in v)/len(v),4)} for k,v in sorted(buckets.items())]}
