from urllib.parse import urlparse
import socket, ipaddress

class URLPolicyError(ValueError): pass

BLOCKED_SCHEMES={"file","javascript","data","chrome","ftp","about"}
METADATA_IPS={ipaddress.ip_address("169.254.169.254")}

def _blocked_ip(ip, allow_private=False):
    obj=ipaddress.ip_address(ip)
    if obj in METADATA_IPS:return True
    return (not allow_private) and (obj.is_private or obj.is_loopback or obj.is_link_local or obj.is_reserved or obj.is_multicast)

def validate_url(url, *, allow_private=False, allowed_hosts=None, resolver=socket.getaddrinfo):
    u=urlparse(url)
    if u.scheme.lower() not in {"http","https"}: raise URLPolicyError("BLOCKED_URL_SCHEME")
    if not u.hostname: raise URLPolicyError("MISSING_HOST")
    if allowed_hosts is not None and u.hostname not in set(allowed_hosts): raise URLPolicyError("HOST_NOT_ALLOWLISTED")
    try:
        infos=resolver(u.hostname,u.port or (443 if u.scheme=="https" else 80),type=socket.SOCK_STREAM)
    except socket.gaierror as e:
        raise URLPolicyError("DNS_RESOLUTION_FAILED") from e
    ips={x[4][0] for x in infos}
    if any(_blocked_ip(ip,allow_private=allow_private) for ip in ips):
        raise URLPolicyError("SSRF_BLOCKED")
    return {"status":"PASS","url":url,"resolved_ips":sorted(ips)}

def validate_redirect(url, **kwargs):
    return validate_url(url, **kwargs)
