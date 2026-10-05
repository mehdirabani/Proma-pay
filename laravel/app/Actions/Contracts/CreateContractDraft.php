<?php

namespace App\Actions\Contracts;

use App\Domain\Contracts\ContractPricingCalculator;
use App\Enums\KycStatus;
use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\KycCase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

class CreateContractDraft
{
    public function __construct(private readonly ContractPricingCalculator $calculator) {}

    /**
     * @throws JsonException
     */
    public function handle(Customer $customer, array $input, User $actor): Contract
    {
        $quote = $this->calculator->quote(
            $input['principal_toman'],
            $input['down_payment_toman'],
            $input['monthly_interest_rate'],
            (int) $input['installment_count'],
            $input['interest_type'],
        );

        $scheduledTotal = (int) $quote['total_installments_toman'];
        $downPayment = (int) $quote['down_payment_toman'];
        if ($scheduledTotal === PHP_INT_MAX || $downPayment > PHP_INT_MAX - $scheduledTotal) {
            throw ValidationException::withMessages(['principal_toman' => 'مبلغ نهایی از محدودهٔ قابل ذخیره خارج است.']);
        }

        $canonicalInput = [
            'customer_id' => (int) $customer->id,
            'principal_toman' => $quote['principal_toman'],
            'down_payment_toman' => $quote['down_payment_toman'],
            'monthly_rate_units' => $quote['monthly_rate_units'],
            'interest_type' => $quote['interest_type'],
            'installment_count' => $quote['installment_count'],
            'start_date' => $input['start_date'],
            'first_due_date' => $input['first_due_date'],
        ];
        $requestHash = hash('sha256', json_encode($canonicalInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            return DB::transaction(function () use ($customer, $input, $actor, $quote, $scheduledTotal, $downPayment, $requestHash): Contract {
                $sequence = DB::table('domain_sequences')->where('sequence_name', 'contracts')->lockForUpdate()->first();
                if ($sequence === null) {
                    throw new LogicException('The contract sequence row is missing.');
                }

                $existing = Contract::query()->where('request_uuid', $input['request_uuid'])->first();
                if ($existing !== null) {
                    $this->assertSameRequest($existing, $requestHash);

                    return $existing;
                }

                $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
                if ($lockedCustomer->status !== 'active') {
                    throw ValidationException::withMessages(['customer_id' => 'برای مشتری غیرفعال نمی‌توان پیش‌نویس قرارداد ساخت.']);
                }

                $latestKyc = KycCase::query()->where('customer_id', $lockedCustomer->id)->latest('id')->first();
                if ($latestKyc?->status !== KycStatus::Approved) {
                    throw ValidationException::withMessages(['customer_id' => 'ابتدا باید آخرین پروندهٔ احراز هویت مشتری تأیید شود.']);
                }

                $prefix = 'PR';
                $serial = (int) $sequence->next_value;
                do {
                    if ($serial <= 0 || $serial === PHP_INT_MAX) {
                        throw new LogicException('The contract sequence is outside the supported range.');
                    }
                    $contractNumber = $prefix.'-'.str_pad((string) $serial, 6, '0', STR_PAD_LEFT);
                    $numberExists = Contract::query()->where('contract_number', $contractNumber)
                        ->orWhere(fn ($query) => $query->where('prefix', $prefix)->where('serial', $serial))
                        ->exists();
                    if ($numberExists) {
                        $serial++;
                    }
                } while ($numberExists);

                DB::table('domain_sequences')->where('sequence_name', 'contracts')->update([
                    'next_value' => $serial + 1,
                    'updated_at' => now(),
                ]);

                $contract = Contract::create([
                    'customer_id' => $lockedCustomer->id,
                    'branch_id' => $lockedCustomer->branch_id,
                    'created_by' => $actor->id,
                    'contract_number' => $contractNumber,
                    'prefix' => $prefix,
                    'serial' => $serial,
                    'currency_code' => config('proma.currency_code'),
                    'total_amount_toman' => (string) ($downPayment + $scheduledTotal),
                    'down_payment_toman' => (string) $downPayment,
                    'principal_toman' => $quote['principal_toman'],
                    'monthly_interest_rate' => $quote['monthly_rate_percent'],
                    'interest_type' => $quote['interest_type'],
                    'calculation_version' => $quote['calculation_version'],
                    'pricing_snapshot_hash' => hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
                    'request_uuid' => $input['request_uuid'],
                    'request_hash' => $requestHash,
                    'installment_count' => $quote['installment_count'],
                    'start_date' => $input['start_date'],
                    'first_due_date' => $input['first_due_date'],
                    'status' => 'draft',
                ]);

                $batch = [];
                $now = now();
                foreach ($this->calculator->dueDates($input['first_due_date'], $quote['installment_count']) as $number => $dueDate) {
                    $batch[] = [
                        'contract_id' => $contract->id,
                        'installment_number' => $number,
                        'due_date' => $dueDate,
                        'amount_toman' => $quote['installment_amount_toman'],
                        'paid_toman' => 0,
                        'status' => 'draft',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($batch) === 200) {
                        DB::table('installments')->insert($batch);
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    DB::table('installments')->insert($batch);
                }

                AuditEvent::create([
                    'actor_id' => $actor->id,
                    'request_id' => $input['request_uuid'],
                    'event_name' => 'contract.draft_created',
                    'subject_type' => Contract::class,
                    'subject_id' => $contract->id,
                    'metadata' => [
                        'contract_number' => $contractNumber,
                        'principal_toman' => $quote['principal_toman'],
                        'down_payment_toman' => $quote['down_payment_toman'],
                        'installment_count' => $quote['installment_count'],
                        'calculation_version' => $quote['calculation_version'],
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => $now,
                ]);

                return $contract;
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existing = Contract::query()->where('request_uuid', $input['request_uuid'])->first();
            if ($existing !== null) {
                $this->assertSameRequest($existing, $requestHash);

                return $existing;
            }

            throw $exception;
        }
    }

    private function assertSameRequest(Contract $contract, string $requestHash): void
    {
        if (! hash_equals((string) $contract->request_hash, $requestHash)) {
            throw ValidationException::withMessages(['request_uuid' => 'این شناسهٔ درخواست قبلاً با اطلاعات دیگری استفاده شده است.']);
        }
    }
}
