class ParseResult(dict):pass
class BaseCodeParser:
    mode='REDUCED'
    def parse(self,text,path):raise NotImplementedError
