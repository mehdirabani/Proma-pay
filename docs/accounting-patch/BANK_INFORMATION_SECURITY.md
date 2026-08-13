# Bank information security

Only masked last-four values are returned to views. Full card/account values are not logged. The service uses the Core `encrypt_value` helper when available; if the host does not provide it, bank submission is rejected rather than storing reversible plaintext. Production encryption-key and reveal-permission tests are BLOCKED.
