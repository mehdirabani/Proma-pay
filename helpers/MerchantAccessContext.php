<?php

declare(strict_types=1);

/** Trusted tenant identity and exact, non-wildcard API grants for one request. */
final class MerchantAccessContext
{
    private $merchantId;
    private $siteId;
    private $connectionId;
    private $scopes;

    public function __construct(int $merchantId, int $siteId, int $connectionId, array $scopes)
    {
        if ($merchantId < 1 || $siteId < 1 || $connectionId < 1) {
            throw new InvalidArgumentException('merchant_context_invalid');
        }

        $this->merchantId = $merchantId;
        $this->siteId = $siteId;
        $this->connectionId = $connectionId;
        $this->scopes = MerchantApiCredentialToken::normalizeScopes($scopes);
    }

    public function merchantId(): int
    {
        return $this->merchantId;
    }

    public function siteId(): int
    {
        return $this->siteId;
    }

    public function connectionId(): int
    {
        return $this->connectionId;
    }

    public function scopes(): array
    {
        return $this->scopes;
    }

    public function allows(string $requiredScope): bool
    {
        return in_array($requiredScope, $this->scopes, true);
    }

    public function requireScope(string $requiredScope): void
    {
        if (!$this->allows($requiredScope)) {
            throw new DomainException('merchant_scope_denied');
        }
    }
}
