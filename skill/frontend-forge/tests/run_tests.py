from pathlib import Path
import sys,json,unittest,time
ROOT=Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT))
class TimedResult(unittest.TestResult):
    def __init__(self):super().__init__();self._start={};self.timings=[]
    def startTest(self,test):self._start[id(test)]=time.perf_counter();super().startTest(test)
    def stopTest(self,test):
        t=self._start.pop(id(test),None)
        if t is not None:self.timings.append({'test':str(test),'seconds':round(time.perf_counter()-t,4)})
        super().stopTest(test)
loader=unittest.TestLoader();suite=loader.discover(str(ROOT/'tests'),pattern='test_*.py')
start=time.perf_counter();result=TimedResult();suite.run(result);elapsed=round(time.perf_counter()-start,4)
payload={'status':'PASS' if result.wasSuccessful() else 'FAIL','tests_run':result.testsRun,
         'failures':[{'test':str(t),'message':m} for t,m in result.failures],
         'errors':[{'test':str(t),'message':m} for t,m in result.errors],
         'skipped':[str(x) for x in getattr(result,'skipped',[])],
         'elapsed_seconds':elapsed,'slowest_tests':sorted(result.timings,key=lambda x:-x['seconds'])[:10],
         'flaky_status':'UNVERIFIED_SINGLE_RUN'}
print(json.dumps(payload,indent=2));(ROOT/'tests'/'TEST_RESULTS.json').write_text(json.dumps(payload,indent=2)+'\n')
sys.exit(0 if result.wasSuccessful() else 1)
