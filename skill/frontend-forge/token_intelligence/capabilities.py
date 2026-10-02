from .token_meter import meter
from .context_selector import select
from .context_cache import FileSummaryCache
from .compression_engine import compress_logs,compress_typescript,compress_lighthouse
from .module_loader import select_modules
from pathlib import Path
import tempfile, json

def token_meter_capability(ctx):
    parts=ctx.get('parts') or {'text':ctx.get('text','')}
    return {'token_metrics':{'status':'PASS','measurement':'ESTIMATED_TOKEN_USAGE','metrics':meter(parts)}}

def context_selector_capability(ctx):
    return {'context_pack':select(ctx.get('candidates',[]),ctx.get('task',''),int(ctx.get('budget',ctx.get('budget_tokens',4000))),ctx.get('changed_files',()),float(ctx.get('confidence',1.0)))}

def context_cache_capability(ctx):
    cache_path=Path(ctx.get('cache_path') or '.ffx-context-cache.json')
    c=FileSummaryCache(cache_path)
    file=ctx.get('file')
    if not file:return {'summary_cache':{'status':'PARTIAL','hit':False,'reason':'file missing'}}
    hit=c.get(file)
    return {'summary_cache':{'status':'PASS','hit':bool(hit),'entry':hit}}

def context_compressor_capability(ctx):
    kind=ctx.get('kind','logs');raw=ctx.get('tool_output','')
    if kind=='typescript':findings=compress_typescript(raw)
    elif kind=='lighthouse':findings=compress_lighthouse(raw)
    else:findings=compress_logs(raw)
    return {'compressed_findings':{'status':'PASS','kind':kind,'findings':findings}}

def module_loader_capability(ctx):
    r=select_modules(ctx.get('task',''),ctx.get('risk','LOW'));return {'module_trace':{'status':'PASS',**r}}

def token_benchmark_capability(ctx):
    # Lightweight deterministic capability: full benchmark runner is invoked via benchmark script/CI.
    return {'token_efficiency_report':{'status':'PASS','measurement':'ESTIMATED_TOKEN_USAGE','benchmark':'available','path':'benchmarks/token-efficiency/benchmark_runner.py'}}
