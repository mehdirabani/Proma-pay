import argparse,json,traceback
from runtime_py.io_utils import import_callable

def main():
    ap=argparse.ArgumentParser();ap.add_argument('--handler',required=True);ap.add_argument('--input',required=True);ap.add_argument('--output',required=True);a=ap.parse_args()
    try:
        ctx=json.load(open(a.input,encoding='utf-8'));fn=import_callable(a.handler);result=fn(ctx)
        json.dump({"ok":True,"result":result},open(a.output,'w',encoding='utf-8'))
    except Exception as e:
        json.dump({"ok":False,"error":str(e),"traceback":traceback.format_exc()},open(a.output,'w',encoding='utf-8'))
        raise
if __name__=='__main__':main()
