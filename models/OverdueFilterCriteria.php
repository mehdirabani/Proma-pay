<?php

declare(strict_types=1);

/**
 * A typed, whitelisted representation of the overdue-queue URL.
 *
 * Keeping this boundary separate from the controller makes the query
 * shareable, prevents raw URL values from becoming SQL fragments, and gives
 * pagination one predictable source of truth.
 */
final class OverdueFilterCriteria
{
    public const SORTS = [
        'overdue_count_desc', 'overdue_count_asc',
        'delay_desc', 'delay_asc',
        'amount_desc', 'amount_asc',
        'penalty_desc', 'penalty_asc',
        'oldest', 'newest', 'legal_action', 'legal_eligible_newest',
        'name_asc', 'name_desc', 'contract_asc', 'contract_desc',
    ];

    public const BUCKETS = ['today', '1-7', '8-30', '30+'];
    public const CONTRACT_STATUSES = ['active', 'inactive', 'referred', 'closed'];
    public const LEGAL_STATUSES = ['under_legal_review', 'legal', 'notice_sent', 'filed', 'closed'];

    public string $search = '';
    public ?int $customerId = null;
    public ?int $contractId = null;
    public string $contractStatus = '';
    public string $legalStatus = '';
    public string $legalEligible = '';
    public string $legalCaseExists = '';
    public bool $excludeLegalCases = false;
    public ?int $operatorId = null;
    public ?int $lawyerId = null;
    public ?int $minOverdueCount = null;
    public ?int $maxOverdueCount = null;
    public ?int $minOverdueDays = null;
    public ?int $maxOverdueDays = null;
    public ?float $minOverdueAmount = null;
    public ?float $maxOverdueAmount = null;
    public string $dueDateFrom = '';
    public string $dueDateTo = '';
    public string $warningStatus = '';
    public string $promiseState = '';
    public ?int $noContactDays = null;
    public string $bucket = '';
    public string $sort = 'oldest';
    public string $direction = 'asc';
    public int $page = 1;
    public int $perPage = 25;

    public static function fromRequest(array $input): self
    {
        $criteria = new self();
        $criteria->search = self::text($input['q'] ?? $input['search'] ?? '', 120);
        $criteria->customerId = self::positiveInt($input['customer_id'] ?? null);
        $criteria->contractId = self::positiveInt($input['contract_id'] ?? null);
        $criteria->contractStatus = self::allowed($input['contract_status'] ?? '', self::CONTRACT_STATUSES);
        $criteria->legalStatus = self::allowed($input['legal_status'] ?? '', self::LEGAL_STATUSES);
        $criteria->legalEligible = self::allowed($input['legal_eligible'] ?? '', ['yes', 'no']);
        $criteria->legalCaseExists = self::allowed($input['legal_case_exists'] ?? '', ['yes', 'no']);
        $criteria->excludeLegalCases = self::boolean($input['exclude_legal_cases'] ?? false);
        $criteria->operatorId = self::positiveInt($input['operator_id'] ?? null);
        $criteria->lawyerId = self::positiveInt($input['lawyer_id'] ?? null);
        $criteria->minOverdueCount = self::boundedInt($input['min_overdue_count'] ?? null, 0, 10000);
        $criteria->maxOverdueCount = self::boundedInt($input['max_overdue_count'] ?? null, 0, 10000);
        $criteria->minOverdueDays = self::boundedInt($input['min_overdue_days'] ?? null, 0, 36500);
        $criteria->maxOverdueDays = self::boundedInt($input['max_overdue_days'] ?? null, 0, 36500);
        $criteria->minOverdueAmount = self::money($input['min_overdue_amount'] ?? null);
        $criteria->maxOverdueAmount = self::money($input['max_overdue_amount'] ?? null);
        $criteria->dueDateFrom = self::date($input['due_date_from'] ?? $input['due_from'] ?? '');
        $criteria->dueDateTo = self::date($input['due_date_to'] ?? $input['due_to'] ?? '');
        $criteria->warningStatus = self::allowed($input['warning_status'] ?? '', ['sent', 'not_sent']);
        $criteria->promiseState = self::allowed($input['promise_state'] ?? '', ['active', 'expired', 'none']);
        $criteria->noContactDays = self::boundedInt($input['no_contact_days'] ?? null, 1, 3650);
        $criteria->bucket = self::allowed($input['bucket'] ?? '', self::BUCKETS);
        $criteria->sort = self::allowed($input['sort'] ?? '', self::SORTS) ?: 'oldest';
        $criteria->direction = self::allowed($input['direction'] ?? '', ['asc', 'desc']) ?: self::defaultDirection($criteria->sort);
        $criteria->page = self::boundedInt($input['page'] ?? null, 1, 100000) ?? 1;
        $requestedPerPage = self::boundedInt($input['per_page'] ?? null, 1, 100) ?? 25;
        $criteria->perPage = in_array($requestedPerPage, [25, 50, 100], true) ? $requestedPerPage : 25;

        self::normaliseRanges($criteria);
        return $criteria;
    }

    public function queryParameters(bool $includePage = true): array
    {
        $values = [
            'q' => $this->search ?: null,
            'customer_id' => $this->customerId,
            'contract_id' => $this->contractId,
            'contract_status' => $this->contractStatus ?: null,
            'legal_status' => $this->legalStatus ?: null,
            'legal_eligible' => $this->legalEligible ?: null,
            'legal_case_exists' => $this->legalCaseExists ?: null,
            'exclude_legal_cases' => $this->excludeLegalCases ? '1' : null,
            'operator_id' => $this->operatorId,
            'lawyer_id' => $this->lawyerId,
            'min_overdue_count' => $this->minOverdueCount,
            'max_overdue_count' => $this->maxOverdueCount,
            'min_overdue_days' => $this->minOverdueDays,
            'max_overdue_days' => $this->maxOverdueDays,
            'min_overdue_amount' => $this->minOverdueAmount,
            'max_overdue_amount' => $this->maxOverdueAmount,
            'due_date_from' => $this->dueDateFrom ?: null,
            'due_date_to' => $this->dueDateTo ?: null,
            'warning_status' => $this->warningStatus ?: null,
            'promise_state' => $this->promiseState ?: null,
            'no_contact_days' => $this->noContactDays,
            'bucket' => $this->bucket ?: null,
            'sort' => $this->sort === 'oldest' ? null : $this->sort,
            'direction' => $this->direction === self::defaultDirection($this->sort) ? null : $this->direction,
            'per_page' => $this->perPage === 25 ? null : $this->perPage,
        ];
        if ($includePage && $this->page > 1) {
            $values['page'] = $this->page;
        }
        return array_filter($values, static fn ($value): bool => $value !== null && $value !== '');
    }

    public function activeFilterCount(): int
    {
        $values = $this->queryParameters(false);
        unset($values['sort'], $values['direction'], $values['per_page']);
        return count($values);
    }

    private static function normaliseRanges(self $criteria): void
    {
        if ($criteria->minOverdueCount !== null && $criteria->maxOverdueCount !== null && $criteria->minOverdueCount > $criteria->maxOverdueCount) {
            [$criteria->minOverdueCount, $criteria->maxOverdueCount] = [$criteria->maxOverdueCount, $criteria->minOverdueCount];
        }
        if ($criteria->minOverdueDays !== null && $criteria->maxOverdueDays !== null && $criteria->minOverdueDays > $criteria->maxOverdueDays) {
            [$criteria->minOverdueDays, $criteria->maxOverdueDays] = [$criteria->maxOverdueDays, $criteria->minOverdueDays];
        }
        if ($criteria->minOverdueAmount !== null && $criteria->maxOverdueAmount !== null && $criteria->minOverdueAmount > $criteria->maxOverdueAmount) {
            [$criteria->minOverdueAmount, $criteria->maxOverdueAmount] = [$criteria->maxOverdueAmount, $criteria->minOverdueAmount];
        }
        if ($criteria->dueDateFrom !== '' && $criteria->dueDateTo !== '' && $criteria->dueDateFrom > $criteria->dueDateTo) {
            [$criteria->dueDateFrom, $criteria->dueDateTo] = [$criteria->dueDateTo, $criteria->dueDateFrom];
        }
    }

    private static function text($value, int $maxLength): string
    {
        $value = trim((string) $value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
    }

    private static function positiveInt($value): ?int
    {
        return self::boundedInt($value, 1, PHP_INT_MAX);
    }

    private static function boundedInt($value, int $min, int $max): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        $value = trim(to_english_digits((string) $value));
        if (!preg_match('/^\d+$/', $value)) return null;
        return max($min, min($max, (int) $value));
    }

    private static function money($value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;
        $value = trim(to_english_digits((string) $value));
        if (!preg_match('/^[\d,]+(?:\.\d{1,2})?$/', $value)) return null;
        return max(0, normalize_money($value));
    }

    private static function date($value): string
    {
        return parse_jalali_date($value) ?: '';
    }

    private static function allowed($value, array $allowed): string
    {
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : '';
    }

    private static function boolean($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function defaultDirection(string $sort): string
    {
        return in_array($sort, ['overdue_count_desc', 'delay_desc', 'amount_desc', 'penalty_desc', 'newest', 'legal_eligible_newest', 'name_desc', 'contract_desc'], true) ? 'desc' : 'asc';
    }
}
