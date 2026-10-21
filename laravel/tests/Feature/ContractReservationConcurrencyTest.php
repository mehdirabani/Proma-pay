<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\CreditAccount;
use App\Models\Installment;
use App\Models\KycCase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class ContractReservationConcurrencyTest extends TestCase
{
    use CreatesPromaUsers;

    public function test_concurrent_contract_submissions_cannot_over_reserve_credit(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('A real row-locking database is required for this concurrency proof.');
        }

        $database = config('database.connections.mysql');
        if (($database['host'] ?? null) !== '127.0.0.1'
            || (string) ($database['port'] ?? '') !== '3307'
            || ($database['database'] ?? null) !== 'proma_pay_laravel_test') {
            $this->fail('Concurrency tests may only use the isolated loopback MariaDB test database.');
        }

        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = true;
        $this->beforeApplicationDestroyed(function (): void {
            $this->artisan('migrate:rollback');
            RefreshDatabaseState::$migrated = false;
        });

        $branch = $this->createBranch();
        $actor = $this->createActor('administrator', $branch);
        $customer = $this->createCustomer($branch);
        CreditAccount::query()->where('customer_id', $customer->id)->update(['limit_toman' => 1_000_000]);
        KycCase::create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'identity_provider' => 'manual',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $actor->id,
        ]);

        $contracts = collect([1, 2])->map(function (int $index) use ($actor, $branch, $customer): Contract {
            $contract = Contract::create([
                'customer_id' => $customer->id,
                'branch_id' => $branch->id,
                'created_by' => $actor->id,
                'contract_number' => 'RACE-'.Str::uuid(),
                'prefix' => 'RACE',
                'serial' => $index,
                'currency_code' => 'TMN',
                'total_amount_toman' => 700_000,
                'down_payment_toman' => 0,
                'principal_toman' => 700_000,
                'monthly_interest_rate' => '0',
                'interest_type' => 'simple',
                'calculation_version' => 'legacy-contract-finance-v1',
                'pricing_snapshot_hash' => hash('sha256', 'race-'.$index),
                'request_uuid' => (string) Str::uuid(),
                'request_hash' => hash('sha256', 'create-'.$index),
                'installment_count' => 1,
                'start_date' => now()->toDateString(),
                'first_due_date' => now()->addDay()->toDateString(),
                'status' => 'draft',
            ]);

            Installment::create([
                'contract_id' => $contract->id,
                'installment_number' => 1,
                'due_date' => now()->addDay()->toDateString(),
                'amount_toman' => 700_000,
                'paid_toman' => 0,
                'status' => 'draft',
            ]);

            return $contract;
        });

        $barrierDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'proma-credit-race-'.Str::uuid();
        mkdir($barrierDirectory, 0700, true);
        $processes = [];

        try {
            foreach ($contracts as $index => $contract) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/contract_reservation_worker.php'),
                    (string) $contract->id,
                    (string) Str::uuid(),
                    (string) $actor->id,
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
                        $this->fail('A reservation worker stopped before reaching the barrier: '.$process->getErrorOutput().$process->getOutput());
                    }
                }

                if (microtime(true) >= $deadline) {
                    $this->fail('Concurrent reservation workers did not reach the start barrier.');
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

        $accepted = collect($outcomes)->where('outcome', 'accepted');
        $rejected = collect($outcomes)->where('outcome', 'rejected');
        $this->assertCount(1, $accepted);
        $this->assertCount(1, $rejected);
        $this->assertArrayHasKey('credit_account', $rejected->first()['errors']);

        $account = CreditAccount::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('700000', (string) $account->reserved_toman);
        $this->assertSame(['draft', 'pending_approval'], Contract::query()->orderBy('id')->pluck('status')->sort()->values()->all());
        $this->assertDatabaseCount('credit_ledger_entries', 1);
        $this->assertDatabaseCount('contract_status_history', 1);
    }
}
