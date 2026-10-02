# Threat Model

Runtime worker compromise: cannot directly read signer API contract; broker auth/replay checks constrain signing.
Project compromise: bounded by full sandbox requirement and URL/network policy.
Adapter compromise: code hash registry detects substitution; adapter cannot self-sign.
Broker compromise: high impact; rotate broker credential/key and review receipts.
Signer compromise: revoke key; dependent evidence becomes REQUIRES_REVIEW.
Database compromise: event/session/checkpoint integrity must reject resume.
