<?php

declare(strict_types=1);

/** Tenant-scoped API reads, request idempotency, pending order intake, and webhook outbox. */
final class MerchantApiGatewayRepository
{
    public static function consumeRateLimit(MerchantAccessContext $context): array
    {
        $connection = self::connectionRow($context);
        $limit = max(1, min(10000, (int) $connection['rate_limit_per_minute']));
        $bucket = (new DateTimeImmutable('now'))->format('Y-m-d H:i:00');
        if (random_int(1, 100) === 1) {
            Model::execute(
                'DELETE FROM merchant_api_rate_limits
                 WHERE connection_id = ? AND window_started_at < DATE_SUB(NOW(), INTERVAL 1 DAY)',
                [$context->connectionId()]
            );
        }
        Model::execute(
            'INSERT INTO merchant_api_rate_limits (connection_id, window_started_at, request_count)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1',
            [$context->connectionId(), $bucket]
        );
        $row = Model::fetch(
            'SELECT request_count FROM merchant_api_rate_limits
             WHERE connection_id = ? AND window_started_at = ?',
            [$context->connectionId(), $bucket]
        );
        $count = (int) ($row['request_count'] ?? ($limit + 1));
        return [
            'allowed' => $count <= $limit,
            'limit' => $limit,
            'remaining' => max(0, $limit - $count),
            'retry_after' => max(1, 60 - (int) (new DateTimeImmutable('now'))->format('s')),
        ];
    }

    public static function connectionSummary(MerchantAccessContext $context): array
    {
        $row = self::connectionRow($context);
        return [
            'connection_uuid' => (string) $row['connection_uuid'],
            'connection_code' => (string) $row['connection_code'],
            'display_name' => (string) $row['connection_name'],
            'api_version' => (string) $row['api_version'],
            'status' => (string) $row['status'],
            'rate_limit_per_minute' => (int) $row['rate_limit_per_minute'],
            'merchant_uuid' => (string) $row['merchant_uuid'],
            'site_uuid' => (string) $row['site_uuid'],
            'site_origin' => (string) $row['site_origin'],
            'verification_status' => (string) $row['verification_status'],
            'environment' => 'test',
        ];
    }

    public static function financialPolicy(MerchantAccessContext $context): array
    {
        $row = Model::fetch(
            "SELECT p.id AS policy_version_id, p.policy_version_uuid, p.content_sha256, p.policy_json
             FROM merchant_policy_heads h
             INNER JOIN merchant_policy_versions p
               ON p.id = h.policy_version_id AND p.connection_id = h.connection_id
              AND p.policy_type = h.policy_type AND p.status = 'published'
             WHERE h.connection_id = ? AND h.policy_type = 'financial'
             LIMIT 1",
            [$context->connectionId()]
        );
        if (!$row) {
            throw new DomainException('merchant_api_financial_policy_unavailable');
        }
        try {
            $document = json_decode((string) $row['policy_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DomainException('merchant_api_financial_policy_invalid', 0, $e);
        }
        if (!is_array($document) || !$document) {
            throw new DomainException('merchant_api_financial_policy_invalid');
        }
        return [
            'policy_version_id' => (int) $row['policy_version_id'],
            'policy_version_uuid' => (string) $row['policy_version_uuid'],
            'content_sha256' => (string) $row['content_sha256'],
            'document' => $document,
        ];
    }

    /** Return only the current, published cheque terms needed for customer disclosure. */
    public static function publicGuaranteePolicy(MerchantAccessContext $context): array
    {
        $row = Model::fetch(
            "SELECT p.policy_version_uuid, p.content_sha256, p.policy_json
             FROM merchant_policy_heads h
             INNER JOIN merchant_policy_versions p
               ON p.id = h.policy_version_id AND p.connection_id = h.connection_id
              AND p.policy_type = h.policy_type AND p.status = 'published'
             WHERE h.connection_id = ? AND h.policy_type = 'guarantee'
             LIMIT 1",
            [$context->connectionId()]
        );
        if (!$row) {
            throw new DomainException('merchant_api_guarantee_policy_unavailable');
        }
        try {
            $connectionDocument = json_decode((string) $row['policy_json'], true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new DomainException('merchant_api_guarantee_policy_invalid', 0, $error);
        }
        if (!is_array($connectionDocument)) {
            throw new DomainException('merchant_api_guarantee_policy_invalid');
        }
        $central = MerchantCentralGuaranteePolicyRepository::currentPublished();
        $resolved = MerchantGuaranteePolicy::resolve($connectionDocument, $central);
        $document = [
            'guarantee_type' => $resolved['guarantee_type'],
            'required_cheque_count' => $resolved['required_cheque_count'],
            'guarantee_base' => $resolved['guarantee_base'],
            'multiplier_units' => $resolved['multiplier_units'],
            'multiplier_scale' => $resolved['multiplier_scale'],
            'central_minimum_multiplier_units' => $resolved['central_minimum_multiplier_units'],
            'central_maximum_multiplier_units' => $resolved['central_maximum_multiplier_units'],
            'beneficiary_legal_name' => $resolved['beneficiary_legal_name'],
            'beneficiary_identifier' => $resolved['beneficiary_identifier'],
            'central_policy_uuid' => (string) $central['policy_uuid'],
            'central_policy_version_no' => (int) $central['version_no'],
        ];
        $canonical = json_encode(
            $document,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
        return [
            'policy_type' => 'guarantee',
            'policy_version_uuid' => (string) $row['policy_version_uuid'],
            'source_policy_sha256' => (string) $row['content_sha256'],
            'policy_sha256' => hash('sha256', $canonical),
            'document' => $document,
        ];
    }

    public static function createOrderRequest(
        MerchantAccessContext $context,
        string $idempotencyKey,
        string $requestHash,
        string $externalOrderId,
        array $input
    ): array {
        return self::withIdempotency(
            $context,
            $idempotencyKey,
            'POST',
            '/orders',
            $requestHash,
            static function () use ($context, $requestHash, $externalOrderId, $input): array {
                $existing = Model::fetch(
                    'SELECT order_uuid, status, request_sha256, quote_json
                     FROM merchant_api_order_requests
                     WHERE connection_id = ? AND external_order_id = ? FOR UPDATE',
                    [$context->connectionId(), $externalOrderId]
                );
                if ($existing) {
                    if (!hash_equals((string) $existing['request_sha256'], $requestHash)) {
                        throw new DomainException('merchant_api_external_order_conflict');
                    }
                    return [
                        'order_uuid' => (string) $existing['order_uuid'],
                        'external_order_id' => $externalOrderId,
                        'status' => (string) $existing['status'],
                        'quote' => json_decode((string) $existing['quote_json'], true, 64, JSON_THROW_ON_ERROR),
                        'idempotent' => true,
                    ];
                }

                $orderUuid = MerchantAuditLog::newUuid();
                $quoteRequest = [
                    'cash_price_toman' => $input['cash_price_toman'] ?? null,
                    'down_payment_toman' => $input['down_payment_toman'] ?? null,
                    'months' => $input['months'] ?? null,
                    'interest_method' => $input['interest_method'] ?? null,
                ];
                $quote = self::consumeQuote($context, (string) $input['quote_uuid'], $quoteRequest, $orderUuid);
                $policy = Model::fetch(
                    "SELECT id AS policy_version_id, policy_version_uuid, content_sha256
                     FROM merchant_policy_versions
                     WHERE connection_id = ? AND policy_type = 'financial' AND policy_version_uuid = ?
                     LIMIT 1 FOR UPDATE",
                    [$context->connectionId(), (string) $quote['policy_version_uuid']]
                );
                if (!$policy || !hash_equals((string) $policy['content_sha256'], (string) $quote['policy_sha256'])) {
                    throw new DomainException('merchant_api_quote_policy_version_not_found');
                }
                $rateUnits = $quote['calculation']['monthly_interest_rate_units'] ?? null;
                if (!is_int($rateUnits) || $rateUnits < 0 || $rateUnits > 1000000 || $rateUnits % 100 !== 0) {
                    throw new DomainException('merchant_api_quote_policy_invalid');
                }
                $quoteJson = self::encodeJson($quote);
                $payload = [
                    'external_order_id' => $externalOrderId,
                    'quote_uuid' => (string) $input['quote_uuid'],
                    'cash_price_toman' => $input['cash_price_toman'],
                    'down_payment_toman' => $input['down_payment_toman'],
                    'months' => $input['months'],
                    'interest_method' => $input['interest_method'],
                ];
                Model::execute(
                    "INSERT INTO merchant_api_order_requests
                        (order_uuid, merchant_id, site_id, connection_id, external_order_id,
                         status, contract_request_status, financial_policy_version_id, financial_policy_type,
                         financial_policy_version_uuid, financial_policy_sha256,
                         cash_price_toman, down_payment_toman, financed_principal_toman, months,
                         monthly_rate_bps, interest_method, request_sha256, request_json, quote_json)
                     VALUES (?, ?, ?, ?, ?, 'pending_review', 'not_requested', ?, 'financial', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $orderUuid,
                        $context->merchantId(),
                        $context->siteId(),
                        $context->connectionId(),
                        $externalOrderId,
                        $policy['policy_version_id'],
                        $policy['policy_version_uuid'],
                        $policy['content_sha256'],
                        $input['cash_price_toman'],
                        $input['down_payment_toman'],
                        $quote['calculation']['financed_principal'],
                        $input['months'],
                        intdiv($rateUnits, 100),
                        $input['interest_method'],
                        $requestHash,
                        self::encodeJson($payload),
                        $quoteJson,
                    ]
                );
                self::enqueueWebhook($context, 'order.pending_review', $orderUuid, [
                    'order_uuid' => $orderUuid,
                    'external_order_id' => $externalOrderId,
                    'status' => 'pending_review',
                ]);
                MerchantAuditLog::append(
                    $context->merchantId(), $context->siteId(), $context->connectionId(), null,
                    'api.order_request.created', 'merchant_api_order_request', $orderUuid,
                    ['order_uuid' => $orderUuid, 'status' => 'pending_review']
                );
                return [
                    'order_uuid' => $orderUuid,
                    'external_order_id' => $externalOrderId,
                    'status' => 'pending_review',
                    'quote' => $quote,
                    'idempotent' => false,
                ];
            }
        );
    }

    /** Store the exact server-calculated quote so later policy changes cannot alter customer-accepted terms. */
    public static function issueQuote(MerchantAccessContext $context, array $input, array $quote): void
    {
        $quoteUuid = strtolower((string) ($quote['quote_uuid'] ?? ''));
        $expiresAt = (string) ($quote['expires_at'] ?? '');
        if (!self::validApiUuid($quoteUuid)
            || !is_array($quote['calculation'] ?? null)
            || !is_array($quote['guarantee'] ?? null)) {
            throw new DomainException('merchant_api_quote_snapshot_invalid');
        }
        try {
            $expiresEpoch = (new DateTimeImmutable($expiresAt))->getTimestamp();
        } catch (Throwable $error) {
            throw new DomainException('merchant_api_quote_expiry_invalid', 0, $error);
        }
        if ($expiresEpoch <= time() || $expiresEpoch > time() + 3600) {
            throw new DomainException('merchant_api_quote_expiry_invalid');
        }
        $requestHash = MerchantApiGateway::canonicalRequestHash('POST', '/quotes', $input);
        Model::execute(
            'INSERT INTO merchant_api_quotes
                (quote_uuid,merchant_id,site_id,connection_id,policy_version_uuid,request_sha256,request_json,quote_json,expires_at_epoch)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $quoteUuid, $context->merchantId(), $context->siteId(), $context->connectionId(),
                (string) $quote['policy_version_uuid'], $requestHash, self::encodeJson($input),
                self::encodeJson($quote), $expiresEpoch,
            ]
        );
        if (random_int(1, 100) === 1) {
            Model::execute(
                'DELETE FROM merchant_api_quotes WHERE expires_at_epoch < ? LIMIT 500',
                [time() - 2592000]
            );
        }
    }

    /** Consume one unexpired quote for the same connection and exact checkout inputs, inside the order transaction. */
    private static function consumeQuote(
        MerchantAccessContext $context,
        string $quoteUuid,
        array $input,
        string $orderUuid
    ): array {
        if (!self::validApiUuid($quoteUuid)) {
            throw new DomainException('merchant_api_quote_uuid_invalid');
        }
        $row = Model::fetch(
            'SELECT quote_uuid,policy_version_uuid,request_sha256,quote_json,expires_at_epoch,consumed_order_uuid
             FROM merchant_api_quotes
             WHERE quote_uuid = ? AND merchant_id = ? AND site_id = ? AND connection_id = ?
             LIMIT 1 FOR UPDATE',
            [$quoteUuid, $context->merchantId(), $context->siteId(), $context->connectionId()]
        );
        if (!$row) {
            throw new DomainException('merchant_api_quote_not_found');
        }
        if ((int) $row['expires_at_epoch'] <= time()) {
            throw new DomainException('merchant_api_quote_expired');
        }
        $requestHash = MerchantApiGateway::canonicalRequestHash('POST', '/quotes', $input);
        if (!hash_equals((string) $row['request_sha256'], $requestHash)) {
            throw new DomainException('merchant_api_quote_input_conflict');
        }
        if ($row['consumed_order_uuid'] !== null && !hash_equals((string) $row['consumed_order_uuid'], $orderUuid)) {
            throw new DomainException('merchant_api_quote_already_consumed');
        }
        $quote = json_decode((string) $row['quote_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($quote) || !hash_equals((string) $row['policy_version_uuid'], (string) ($quote['policy_version_uuid'] ?? ''))) {
            throw new DomainException('merchant_api_quote_snapshot_invalid');
        }
        if ($row['consumed_order_uuid'] === null) {
            Model::execute(
                'UPDATE merchant_api_quotes SET consumed_order_uuid = ?, consumed_at = UTC_TIMESTAMP()
                 WHERE quote_uuid = ? AND consumed_order_uuid IS NULL',
                [$orderUuid, $quoteUuid]
            );
        }
        return $quote;
    }

    private static function validApiUuid(string $uuid): bool
    {
        return (bool) preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $uuid);
    }

    public static function submitContractRequest(
        MerchantAccessContext $context,
        string $idempotencyKey,
        string $requestHash,
        string $orderUuid,
        string $externalCustomerReference
    ): array {
        return self::withIdempotency(
            $context,
            $idempotencyKey,
            'POST',
            '/contracts',
            $requestHash,
            static function () use ($context, $orderUuid, $externalCustomerReference): array {
                $order = Model::fetch(
                    'SELECT id, order_uuid, external_order_id, status, contract_request_status
                     FROM merchant_api_order_requests
                     WHERE order_uuid = ? AND connection_id = ? AND merchant_id = ? AND site_id = ? FOR UPDATE',
                    [$orderUuid, $context->connectionId(), $context->merchantId(), $context->siteId()]
                );
                if (!$order) {
                    throw new DomainException('merchant_api_order_not_found');
                }
                if ($order['status'] !== 'pending_review' || $order['contract_request_status'] !== 'not_requested') {
                    throw new DomainException('merchant_api_contract_request_not_available');
                }
                $contractRequestUuid = MerchantAuditLog::newUuid();
                Model::execute(
                    "UPDATE merchant_api_order_requests
                     SET contract_request_uuid = ?, contract_request_status = 'pending_review',
                         external_customer_reference_hash = ?, updated_at = NOW()
                     WHERE id = ? AND contract_request_status = 'not_requested'",
                    [$contractRequestUuid, hash('sha256', $externalCustomerReference), (int) $order['id']]
                );
                self::enqueueWebhook($context, 'contract.pending_review', $orderUuid, [
                    'order_uuid' => $orderUuid,
                    'contract_request_uuid' => $contractRequestUuid,
                    'external_order_id' => (string) $order['external_order_id'],
                    'status' => 'pending_review',
                ]);
                MerchantAuditLog::append(
                    $context->merchantId(), $context->siteId(), $context->connectionId(), null,
                    'api.contract_request.created', 'merchant_api_order_request', $orderUuid,
                    ['contract_request_uuid' => $contractRequestUuid, 'status' => 'pending_review']
                );
                return [
                    'order_uuid' => $orderUuid,
                    'contract_request_uuid' => $contractRequestUuid,
                    'external_order_id' => (string) $order['external_order_id'],
                    'status' => 'pending_review',
                    'idempotent' => false,
                ];
            }
        );
    }

    public static function cancelOrderRequest(
        MerchantAccessContext $context,
        string $idempotencyKey,
        string $requestHash,
        string $requestPath,
        string $orderUuid,
        string $reasonCode
    ): array {
        return self::withIdempotency(
            $context,
            $idempotencyKey,
            'POST',
            $requestPath,
            $requestHash,
            static function () use ($context, $orderUuid, $reasonCode): array {
                $order = Model::fetch(
                    'SELECT id, external_order_id, status, contract_request_status
                     FROM merchant_api_order_requests
                     WHERE order_uuid = ? AND connection_id = ? AND merchant_id = ? AND site_id = ? FOR UPDATE',
                    [$orderUuid, $context->connectionId(), $context->merchantId(), $context->siteId()]
                );
                if (!$order) {
                    throw new DomainException('merchant_api_order_not_found');
                }
                if ((string) $order['status'] === 'cancelled') {
                    return [
                        'order_uuid' => $orderUuid,
                        'external_order_id' => (string) $order['external_order_id'],
                        'status' => 'cancelled',
                        'idempotent' => true,
                    ];
                }
                if ((string) $order['status'] !== 'pending_review') {
                    throw new DomainException('merchant_api_order_cancellation_not_available');
                }
                $contract = Model::fetch(
                    'SELECT id FROM merchant_contract_links
                     WHERE connection_id = ? AND merchant_id = ? AND site_id = ? AND external_order_id = ? LIMIT 1',
                    [$context->connectionId(), $context->merchantId(), $context->siteId(), (string) $order['external_order_id']]
                );
                if ($contract) {
                    throw new DomainException('merchant_api_order_contract_already_created');
                }
                Model::execute(
                    "UPDATE merchant_api_order_requests SET status = 'cancelled', updated_at = NOW()
                     WHERE id = ? AND status = 'pending_review'",
                    [(int) $order['id']]
                );
                self::enqueueWebhook($context, 'order.cancelled', $orderUuid, [
                    'order_uuid' => $orderUuid,
                    'external_order_id' => (string) $order['external_order_id'],
                    'status' => 'cancelled',
                    'reason_code' => $reasonCode,
                ]);
                MerchantAuditLog::append(
                    $context->merchantId(), $context->siteId(), $context->connectionId(), null,
                    'api.order_request.cancelled', 'merchant_api_order_request', $orderUuid,
                    ['order_uuid' => $orderUuid, 'reason_code' => $reasonCode]
                );
                return [
                    'order_uuid' => $orderUuid,
                    'external_order_id' => (string) $order['external_order_id'],
                    'status' => 'cancelled',
                    'idempotent' => false,
                ];
            }
        );
    }

    public static function contractStatus(MerchantAccessContext $context, string $orderUuid): array
    {
        $row = Model::fetch(
            'SELECT r.order_uuid, r.external_order_id, r.status AS request_status,
                    r.contract_request_uuid, r.contract_request_status,
                    l.contract_id, c.status AS contract_status
             FROM merchant_api_order_requests r
             LEFT JOIN merchant_contract_links l ON l.connection_id = r.connection_id
                AND l.merchant_id = r.merchant_id AND l.site_id = r.site_id
                AND l.external_order_id = r.external_order_id
             LEFT JOIN contracts c ON c.id = l.contract_id
             WHERE r.order_uuid = ? AND r.connection_id = ? AND r.merchant_id = ? AND r.site_id = ?
             LIMIT 1',
            [$orderUuid, $context->connectionId(), $context->merchantId(), $context->siteId()]
        );
        if (!$row) {
            throw new DomainException('merchant_api_contract_not_found');
        }
        return [
            'order_uuid' => (string) $row['order_uuid'],
            'external_order_id' => (string) $row['external_order_id'],
            'request_status' => (string) $row['request_status'],
            'contract_request_uuid' => $row['contract_request_uuid'] ? (string) $row['contract_request_uuid'] : null,
            'contract_request_status' => (string) $row['contract_request_status'],
            'contract_id' => $row['contract_id'] !== null ? (int) $row['contract_id'] : null,
            'contract_status' => $row['contract_status'] !== null ? (string) $row['contract_status'] : null,
        ];
    }

    /** Return a completed response before mutable policy validation or quote recomputation. */
    public static function replayIdempotent(
        MerchantAccessContext $context,
        string $key,
        string $method,
        string $path,
        string $requestHash
    ): ?array {
        Model::begin();
        try {
            $row = self::idempotencyRow($context, $key, true);
            if (!$row) {
                Model::commit();
                return null;
            }
            $result = self::replayOrReject($row, $method, $path, $requestHash);
            Model::commit();
            return $result;
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    public static function guaranteeStatus(MerchantAccessContext $context, string $orderUuid): array
    {
        $row = Model::fetch(
            'SELECT r.order_uuid, r.external_order_id, g.guarantee_uuid, g.guarantee_type,
                    g.required_cheque_count, g.guarantee_base, g.scheduled_installments_toman,
                    g.cheque_amount_rial, g.first_installment_due_date, g.cheque_due_date,
                    g.sayad_status, g.physical_status
             FROM merchant_api_order_requests r
             LEFT JOIN merchant_contract_links l ON l.connection_id = r.connection_id
                AND l.merchant_id = r.merchant_id AND l.site_id = r.site_id
                AND l.external_order_id = r.external_order_id
             LEFT JOIN merchant_cheque_guarantees g ON g.contract_link_id = l.id
             WHERE r.order_uuid = ? AND r.connection_id = ? AND r.merchant_id = ? AND r.site_id = ?
             LIMIT 1',
            [$orderUuid, $context->connectionId(), $context->merchantId(), $context->siteId()]
        );
        if (!$row) {
            throw new DomainException('merchant_api_guarantee_not_found');
        }
        if (!$row['guarantee_uuid']) {
            return [
                'order_uuid' => (string) $row['order_uuid'],
                'external_order_id' => (string) $row['external_order_id'],
                'status' => 'not_created',
            ];
        }
        return [
            'order_uuid' => (string) $row['order_uuid'],
            'external_order_id' => (string) $row['external_order_id'],
            'guarantee_uuid' => (string) $row['guarantee_uuid'],
            'guarantee_type' => (string) $row['guarantee_type'],
            'required_cheque_count' => (int) $row['required_cheque_count'],
            'guarantee_base' => (string) $row['guarantee_base'],
            'scheduled_installments_toman' => (int) $row['scheduled_installments_toman'],
            'cheque_amount_rial' => (int) $row['cheque_amount_rial'],
            'first_installment_due_date' => (string) $row['first_installment_due_date'],
            'cheque_due_date' => (string) $row['cheque_due_date'],
            'sayad_status' => (string) $row['sayad_status'],
            'physical_status' => (string) $row['physical_status'],
        ];
    }

    public static function webhookSummary(MerchantAccessContext $context): array
    {
        $rows = Model::fetchAll(
            'SELECT delivery_status, COUNT(*) AS event_count
             FROM merchant_api_webhook_outbox WHERE connection_id = ?
             GROUP BY delivery_status',
            [$context->connectionId()]
        );
        $summary = ['queued' => 0, 'processing' => 0, 'delivered' => 0, 'dead_letter' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['delivery_status'];
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int) $row['event_count'];
            }
        }
        return $summary + ['delivery' => 'outbox_only_until_webhook_transport_is_configured'];
    }

    public static function emergencyDisable(
        MerchantAccessContext $context,
        string $idempotencyKey,
        string $requestHash,
        string $reason
    ): array {
        return self::withIdempotency(
            $context,
            $idempotencyKey,
            'POST',
            '/connection/emergency-disable',
            $requestHash,
            static function () use ($context, $reason): array {
                $row = self::connectionRow($context, true);
                if (!(int) $row['emergency_disabled']) {
                    Model::execute(
                        'UPDATE merchant_connections
                         SET emergency_disabled = 1, emergency_disabled_at = NOW(), emergency_disable_reason = ?
                         WHERE id = ? AND merchant_id = ? AND site_id = ? AND emergency_disabled = 0',
                        [$reason, $context->connectionId(), $context->merchantId(), $context->siteId()]
                    );
                    MerchantAuditLog::append(
                        $context->merchantId(), $context->siteId(), $context->connectionId(), null,
                        'connection.emergency_disabled', 'connection', (string) $row['connection_uuid'],
                        ['reason_code' => $reason]
                    );
                }
                return ['connection_uuid' => (string) $row['connection_uuid'], 'status' => 'emergency_disabled', 'idempotent' => false];
            }
        );
    }

    private static function withIdempotency(
        MerchantAccessContext $context,
        string $key,
        string $method,
        string $path,
        string $requestHash,
        callable $operation
    ): array {
        Model::begin();
        try {
            $existing = self::idempotencyRow($context, $key, true);
            if ($existing) {
                $result = self::replayOrReject($existing, $method, $path, $requestHash);
                Model::commit();
                return $result;
            }
            try {
                Model::execute(
                    "INSERT INTO merchant_api_idempotency
                        (connection_id, idempotency_key, request_method, request_path, request_sha256, state)
                     VALUES (?, ?, ?, ?, ?, 'processing')",
                    [$context->connectionId(), $key, $method, $path, $requestHash]
                );
            } catch (PDOException $e) {
                if (!self::isDuplicateKey($e)) {
                    throw $e;
                }
                $existing = self::idempotencyRow($context, $key, true);
                if (!$existing) {
                    throw $e;
                }
                $result = self::replayOrReject($existing, $method, $path, $requestHash);
                Model::commit();
                return $result;
            }

            $result = $operation();
            $alreadyExisted = (bool) ($result['idempotent'] ?? false);
            unset($result['idempotent']);
            $responseJson = self::encodeJson($result);
            $responseStatus = $path === '/orders' ? 201
                : (in_array($path, ['/contracts','/connection/emergency-disable'], true) ? 202 : 200);
            Model::execute(
                "UPDATE merchant_api_idempotency
                 SET state = 'completed', response_status = ?, response_json = ?, completed_at = NOW()
                 WHERE connection_id = ? AND idempotency_key = ? AND state = 'processing'",
                [$responseStatus, $responseJson, $context->connectionId(), $key]
            );
            Model::commit();
            return $result + ['idempotent' => $alreadyExisted];
        } catch (Throwable $e) {
            Model::rollBack();
            throw $e;
        }
    }

    private static function replayOrReject(array $row, string $method, string $path, string $requestHash): array
    {
        if (!hash_equals((string) $row['request_method'], $method)
            || !hash_equals((string) $row['request_path'], $path)
            || !hash_equals((string) $row['request_sha256'], $requestHash)) {
            throw new DomainException('merchant_api_idempotency_conflict');
        }
        if ($row['state'] !== 'completed' || !is_string($row['response_json'])) {
            throw new DomainException('merchant_api_idempotency_in_progress');
        }
        try {
            $response = json_decode($row['response_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DomainException('merchant_api_idempotency_response_invalid', 0, $e);
        }
        if (!is_array($response)) {
            throw new DomainException('merchant_api_idempotency_response_invalid');
        }
        return $response + ['idempotent' => true];
    }

    private static function idempotencyRow(MerchantAccessContext $context, string $key, bool $lock): ?array
    {
        return Model::fetch(
            'SELECT request_method, request_path, request_sha256, state, response_json
             FROM merchant_api_idempotency WHERE connection_id = ? AND idempotency_key = ?'
            . ($lock ? ' FOR UPDATE' : ''),
            [$context->connectionId(), $key]
        );
    }

    private static function enqueueWebhook(
        MerchantAccessContext $context,
        string $eventType,
        string $sourceUuid,
        array $payload
    ): void {
        $json = self::encodeJson($payload);
        Model::execute(
            'INSERT IGNORE INTO merchant_api_webhook_outbox
                (event_uuid, merchant_id, site_id, connection_id, event_type, source_uuid,
                 payload_json, payload_sha256, delivery_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                MerchantAuditLog::newUuid(),
                $context->merchantId(), $context->siteId(), $context->connectionId(),
                $eventType, $sourceUuid, $json, hash('sha256', $json), 'queued',
            ]
        );
    }

    private static function connectionRow(MerchantAccessContext $context, bool $lock = false): array
    {
        $row = Model::fetch(
            "SELECT c.id, c.connection_uuid, c.connection_code, c.display_name AS connection_name,
                    c.api_version, c.status, c.emergency_disabled, c.rate_limit_per_minute,
                    m.merchant_uuid, m.status AS merchant_status,
                    s.site_uuid, s.site_origin, s.status AS site_status, s.verification_status
             FROM merchant_connections c
             INNER JOIN merchant_sites s ON s.id = c.site_id AND s.merchant_id = c.merchant_id
             INNER JOIN merchant_accounts m ON m.id = c.merchant_id
             WHERE c.id = ? AND c.merchant_id = ? AND c.site_id = ?
             LIMIT 1" . ($lock ? ' FOR UPDATE' : ''),
            [$context->connectionId(), $context->merchantId(), $context->siteId()]
        );
        if (!$row || $row['status'] !== 'active' || (int) $row['emergency_disabled'] === 1
            || $row['site_status'] !== 'active' || $row['verification_status'] !== 'verified'
            || $row['merchant_status'] !== 'active') {
            throw new DomainException('merchant_connection_not_available');
        }
        return $row;
    }

    private static function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function isDuplicateKey(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000'
            || (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}
