from collections import defaultdict,deque
class ProjectGraph:
    def __init__(self):self.out=defaultdict(set);self.rev=defaultdict(set)
    def edge(self,a,b):self.out[a].add(b);self.rev[b].add(a)
    def neighborhood(self,start,depth=1):
        seen={start};q=deque([(start,0)])
        while q:
            n,d=q.popleft()
            if d>=depth:continue
            for x in self.out[n]|self.rev[n]:
                if x not in seen:seen.add(x);q.append((x,d+1))
        return seen
