from abc import ABC,abstractmethod
class EmbeddingRetriever(ABC):
    @abstractmethod
    def index(self,candidates):...
    @abstractmethod
    def search(self,query,candidates):...
class UnavailableEmbeddingRetriever(EmbeddingRetriever):
    def index(self,candidates):return {'status':'UNAVAILABLE'}
    def search(self,query,candidates):return {'status':'UNAVAILABLE','results':[]}
