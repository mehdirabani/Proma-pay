import math,statistics
class EmpiricalConfidenceCalibrator:
    def __init__(self,bins=None):self.bins=bins or []
    def fit(self,pairs,bins=5):
        rows=sorted((float(s),1 if y else 0) for s,y in pairs)
        if not rows:self.bins=[];return self
        n=max(1,len(rows)//bins);out=[]
        for i in range(0,len(rows),n):
            chunk=rows[i:i+n];out.append({'lo':chunk[0][0],'hi':chunk[-1][0],'p':(sum(y for _,y in chunk)+1)/(len(chunk)+2)})
        self.bins=out;return self
    def calibrate(self,score):
        if not self.bins:return max(0,min(1,float(score)))
        matches=[b for b in self.bins if b['lo']<=score<=b['hi']]
        if matches:return matches[0]['p']
        b=min(self.bins,key=lambda x:min(abs(score-x['lo']),abs(score-x['hi'])));return b['p']
    def evaluate(self,pairs):
        if not pairs:return {'mae':None,'brier':None,'ece':None,'count':0}
        vals=[(self.calibrate(s),1 if y else 0) for s,y in pairs]
        mae=sum(abs(p-y) for p,y in vals)/len(vals);brier=sum((p-y)**2 for p,y in vals)/len(vals)
        buckets={}
        for p,y in vals:buckets.setdefault(min(9,int(p*10)),[]).append((p,y))
        ece=sum(len(v)/len(vals)*abs(sum(p for p,_ in v)/len(v)-sum(y for _,y in v)/len(v)) for v in buckets.values())
        return {'mae':round(mae,4),'brier':round(brier,4),'ece':round(ece,4),'count':len(vals),
                'buckets':[{'bucket':k,'n':len(v),'mean_confidence':round(sum(p for p,_ in v)/len(v),4),'accuracy':round(sum(y for _,y in v)/len(v),4)} for k,v in sorted(buckets.items())]}
