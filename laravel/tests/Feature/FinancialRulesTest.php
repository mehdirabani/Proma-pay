<?php

namespace Tests\Feature;

use App\Models\FinancialRule;
use App\Models\FinancialRuleHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;
use Tests\TestSupport\CreatesPromaUsers;

class FinancialRulesTest extends TestCase
{
    use CreatesPromaUsers;
    use RefreshDatabase;

    public function test_financial_rules_are_unconfigured_until_an_authorized_finance_policy_owner_sets_them(): void
    {
        $administrator = $this->createActor('administrator');
        $rules = FinancialRule::query()->findOrFail(1);

        $this->assertFalse($rules->configured());
        $this->assertNull($rules->monthly_penalty_rate);
        $this->assertNull($rules->legal_monthly_penalty_rate);
        $this->assertNull($rules->monthly_reward_rate);
        $this->assertNull($rules->late_penalty_grace_days);

        $this->actingAs($administrator)->get(route('settings.financial-rules.edit'))
            ->assertOk()
            ->assertSee('قواعد مالی هنوز تعیین نشده‌اند')
            ->assertSee('dir="rtl"', false)
            ->assertSee('قواعد مالی', false);
    }

    public function test_finance_role_cannot_manage_global_financial_rules(): void
    {
        $finance = $this->createActor('finance');
        $this->actingAs($finance)->get(route('settings.financial-rules.edit'))->assertForbidden();

        $this->actingAs($finance)->put(route('settings.financial-rules.update'), $this->validInput())
            ->assertForbidden();

        $this->assertDatabaseHas('financial_rules', ['id' => 1, 'version' => 0, 'monthly_penalty_rate' => null]);
        $this->assertDatabaseCount('financial_rule_history', 0);
        $this->assertDatabaseMissing('audit_events', ['event_name' => 'financial_rules.updated']);
    }

    public function test_administrator_can_save_persian_rule_values_and_exact_replay_is_idempotent(): void
    {
        $administrator = $this->createActor('administrator');
        $input = $this->validInput([
            'monthly_penalty_rate' => '۱۰٫۵٪',
            'legal_monthly_penalty_rate' => '۲۰',
            'monthly_reward_rate' => '۰٫۲۵',
            'late_penalty_grace_days' => '۵',
        ]);

        $this->actingAs($administrator)->put(route('settings.financial-rules.update'), $input)
            ->assertRedirect(route('settings.financial-rules.edit'))
            ->assertSessionHas('success');

        $this->actingAs($administrator)->put(route('settings.financial-rules.update'), $input)
            ->assertRedirect(route('settings.financial-rules.edit'));

        $rules = FinancialRule::query()->findOrFail(1);
        $this->assertTrue($rules->configured());
        $this->assertSame('10.5', $rules->monthly_penalty_rate);
        $this->assertSame('20', $rules->legal_monthly_penalty_rate);
        $this->assertSame('0.25', $rules->monthly_reward_rate);
        $this->assertSame(5, $rules->late_penalty_grace_days);
        $this->assertSame(1, $rules->version);
        $this->assertSame('10.5', $rules->calculatorSettings()['monthly_penalty_rate']);
        $this->assertSame(1, FinancialRuleHistory::query()->count());
        $this->assertDatabaseHas('financial_rule_history', [
            'request_id' => $input['request_id'],
            'rule_version' => 1,
            'actor_id' => $administrator->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'request_id' => $input['request_id'],
            'event_name' => 'financial_rules.updated',
            'actor_id' => $administrator->id,
        ]);
    }

    public function test_idempotency_key_cannot_be_reused_for_different_financial_rules(): void
    {
        $administrator = $this->createActor('administrator');
        $input = $this->validInput();
        $this->actingAs($administrator)->put(route('settings.financial-rules.update'), $input)
            ->assertRedirect(route('settings.financial-rules.edit'));

        $this->actingAs($administrator)->put(route('settings.financial-rules.update'), [
            ...$input,
            'monthly_penalty_rate' => '11',
        ])->assertSessionHasErrors('request_id');

        $this->assertDatabaseHas('financial_rules', ['id' => 1, 'version' => 1, 'monthly_penalty_rate' => '10']);
        $this->assertDatabaseCount('financial_rule_history', 1);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_invalid_rate_range_is_rejected_without_creating_a_rule_version(): void
    {
        $administrator = $this->createActor('administrator');
        $this->actingAs($administrator)->from(route('settings.financial-rules.edit'))
            ->put(route('settings.financial-rules.update'), $this->validInput(['legal_monthly_penalty_rate' => '۱۰۰٫۰۰۰۱']))
            ->assertSessionHasErrors('legal_monthly_penalty_rate');

        $this->assertDatabaseHas('financial_rules', ['id' => 1, 'version' => 0, 'monthly_penalty_rate' => null]);
        $this->assertDatabaseCount('financial_rule_history', 0);
    }

    public function test_financial_rule_history_rejects_updates_and_deletes(): void
    {
        $administrator = $this->createActor('administrator');
        $input = $this->validInput();
        $this->actingAs($administrator)->put(route('settings.financial-rules.update'), $input)
            ->assertRedirect(route('settings.financial-rules.edit'));
        $history = FinancialRuleHistory::query()->firstOrFail();

        try {
            $history->update(['rule_version' => 99]);
            $this->fail('Financial rule history updates must be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Financial rule history is immutable.', $exception->getMessage());
        }

        try {
            $history->delete();
            $this->fail('Financial rule history deletes must be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Financial rule history is immutable.', $exception->getMessage());
        }

        $this->assertDatabaseHas('financial_rule_history', ['id' => $history->id, 'rule_version' => 1]);
    }

    private function validInput(array $overrides = []): array
    {
        return array_replace([
            'request_id' => (string) Str::uuid(),
            'monthly_penalty_rate' => '10',
            'legal_monthly_penalty_rate' => '20',
            'monthly_reward_rate' => '0',
            'late_penalty_grace_days' => '3',
        ], $overrides);
    }
}
