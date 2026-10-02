class UnauthorizedProvenanceIssuer(PermissionError): pass
class ProvenanceAuthorityClient:
    def __init__(self,*a,**k): raise UnauthorizedProvenanceIssuer("UNAUTHORIZED_PROVENANCE_ISSUER")
def issue_execution_receipt(*a,**k): raise UnauthorizedProvenanceIssuer("UNAUTHORIZED_PROVENANCE_ISSUER")
def attest_artifact(*a,**k): raise UnauthorizedProvenanceIssuer("UNAUTHORIZED_PROVENANCE_ISSUER")
