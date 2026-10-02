from dataclasses import dataclass,asdict
@dataclass
class ContextMetrics:
    files_considered:int=0;files_loaded:int=0;files_rejected:int=0;lines_loaded:int=0;bytes_loaded:int=0
    context_chunks:int=0;duplicate_chunks:int=0;cache_hits:int=0;retrieval_misses:int=0;unused_chunks:int=0
    def to_dict(self):return asdict(self)
    def waste_ratio(self,irrelevant_tokens=0,duplicate_tokens=0,unused_tokens=0,total_context_tokens=0):
        if total_context_tokens<=0:return 0.0
        return round((irrelevant_tokens+duplicate_tokens+unused_tokens)/total_context_tokens,4)
