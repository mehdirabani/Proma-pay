def expand(index,paths,max_rings=3):
    nodes=index['nodes'];seen=set(paths);front=set(paths);rings=[]
    for _ in range(max_rings):
        nxt=set()
        for p in front:
            n=nodes.get(p,{})
            nxt.update(x for x in n.get('imports',[]) if x in nodes)
            nxt.update(n.get('reverse_imports',[]));nxt.update(n.get('tests',[]));nxt.update(n.get('styles',[]))
        nxt-=seen
        if not nxt:break
        rings.append(sorted(nxt));seen|=nxt;front=nxt
    return {'paths':sorted(seen),'rings':rings,'max_rings':max_rings}
