class RepositoryQuery:
    def __init__(self,index):self.nodes=index['nodes']
    def importers(self,path):return list(self.nodes.get(path,{}).get('reverse_imports',[]))
    def imports(self,path):return [x for x in self.nodes.get(path,{}).get('imports',[]) if x in self.nodes]
    def tests(self,path):return list(self.nodes.get(path,{}).get('tests',[]))
    def styles(self,path):return list(self.nodes.get(path,{}).get('styles',[]))
