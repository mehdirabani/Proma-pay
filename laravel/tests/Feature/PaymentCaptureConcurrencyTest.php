<?php

namespace Tests\Feature;

use App\Actions\Payments\CreateSettlementQuote;
use App\Actions\Payments\UpdateFinancialRules;
use App\Models\Contract;
use App\Models\CreditAccount;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SettlementQuote;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class PaymentCaptureConcurrencyTest extends TestCase
{
    use CreatesPromaUsers;

    public function test_two_concurrent_manual_captures_cannot_double_collect_the_same_installment(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('A real row-locking database is required for this concurrency proof.');
        }

        $database = config('database.connections.mysql');
        if (($database['host'] ?? null) !== '127.0.0.1'
            || (string) ($database['port'] ?? '') !== '3307'
            || ($database['database'] ?? null) !== 'proma_pay_laravel_test') {
            $this->fail('Payment concurrency tests may only use the isolated loopback MariaDB test database.');
        }

        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = true;
        $this->beforeApplicationDestroyed(function (): void {
            $this->artisan('migrate:rollback');
            RefreshDatabaseState::$migrated = false;
        });

        $branch = $this->createBranch('PAYMENT-RACE');
        $actor = $this->createActor('administrator', $branch);
        $customer = $this->createCustomer($branch);
        CreditAccount::query()->where('customer_id', $customer->id)->update(['limit_toman' => 100]);
        app(UpdateFinancialRules::class)->handle([
            'request_id' => (string) Str::uuid(),
            'monthly_penalty_rate' => '0',
            'legal_monthly_penalty_rate' => '0',
            'monthly_reward_rate' => '0',
            'late_penalty_grace_days' => 0,
        ], $actor);

        $contract = Contract::create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'created_by' => $actor->id,
            'contract_number' => 'RACE-'.Str::uuid(),
            'prefix' => 'RACE',
            'serial' => 1,
            'currency_code' => 'TMN',
            'total_amount_toman' => 100,
            'down_payment_toman' => 0,
            'principal_toman' => 100,
            'monthly_interest_rate' => '0',
            'interest_type' => 'simple',
            'calculation_version' => 'legacy-contract-finance-v1',
            'pricing_snapshot_hash' => hash('sha256', 'payment-capture-race'),
            'request_uuid' => (string) Str::uuid(),
            'request_hash' => hash('sha256', 'payment-capture-race'),
            'installment_count' => 1,
            'start_date' => now()->toDateString(),
            'first_due_date' => now()->addDay()->toDateString(),
            'status' => 'active',
        ]);
        $installment = Installment::create([
            'contract_id' => $contract->id,
            'installment_number' => 1,
            'due_date' => now()->addDay()->toDateString(),
            'amount_toman' => 100,
            'paid_toman' => 0,
            'status' => 'pending',
        ]);

        $quoteAction = app(CreateSettlementQuote::class);
        $quotes = collect([1, 2])->map(fn (): SettlementQuote => $quoteAction->handle($contract, [
            'request_id' => (string) Str::uuid(),
            'scope' => 'selected',
            'installment_ids' => [$installment->id],
            'amount_toman' => '100',
        ], $actor));

        $barrierDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'proma-payment-race-'.Str::uuid();
        mkdir($barrierDirectory, 0700, true);
        $processes = [];

        try {
            foreach ($quotes as $index => $quote) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/payment_capture_worker.php'),
                    (string) $quote->quote_uuid,
                    (string) $actor->id,
                    (string) Str::uuid(),
                    $barrierDirectory,
                    (string) $index,
                ], base_path());
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 15;
            while (! is_file($barrierDirectory.DIRECTORY_SEPARATOR.'ready-0')
                || ! is_file($barrierDirectory.DIRECTORY_SEPARATOR.'ready-1')) {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail('A payment worker stopped before reaching the barrier: '.$process->getErrorOutput().$process->getOutput());
                    }
                }

                if (microtime(true) >= $deadline) {
                    $this->fail('Concurrent payment workers did not reach the start barrier.');
                }

                usleep(10_000);
            }

            file_put_contents($barrierDirectory.DIRECTORY_SEPARATOR.'go', 'go');
            $outcomes = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
                $outcomes[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
        } finally {
            foreach (glob($barrierDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($barrierDirectory);
        }

        $outcomes = collect($outcomes);
        $this->assertCount(1, $outcomes->where('outcome', 'accepted'));
        $this->assertCount(1, $outcomes->where('outcome', 'rejected'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseHas('installments', ['id' => $installment->id, 'paid_toman' => 100, 'status' => 'paid']);
        $this->assertDatabaseHas('settlement_quotes', ['contract_id' => $contract->id, 'consumed_at' => null]);
        $this->assertSame(1, Payment::query()->where('status', 'paid')->count());
        $this->assertSame(1, PaymentAllocation::query()->where('installment_id', $installment->id)->count());
    }
}
