# IP block source audit

## Finding

Exact blocking component: **BLOCKED**.

No affected client public IP, CSF output, LFD log, Fail2ban jail state, nftables/iptables rule, LiteSpeed deny list or hosting security event was provided. A modem reconnect changing the public IP is consistent with an IP rule, ISP/NAT path failure or transient routing state; it is not proof of a server ban.

## Required collection

```bash
csf -g AFFECTED_IP
grep -R 'AFFECTED_IP' /var/log/lfd.log /var/log/fail2ban.log /var/log/secure /var/log/messages
fail2ban-client status
fail2ban-client status JAIL_NAME
iptables -S
nft list ruleset
```

For DirectAdmin/shared hosting, export the security incident entry with timestamp, rule/jail name, trigger count and expiry. Do not publish the unmasked IP.

## Narrow remediation rule

No firewall, WAF or ISP range was disabled or broadly allowlisted. Any correction must target the proven rule and preserve brute-force, callback, upload and plugin protections.
