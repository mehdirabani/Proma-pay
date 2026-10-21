<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialRule extends Model
{
    protected $table = 'financial_rules';

    protected $fillable = ['monthly_penalty_rate', 'legal_monthly_penalty_rate', 'monthly_reward_rate', 'late_penalty_grace_days', 'version', 'updated_by'];

    protected function casts(): array
    {
        return [
            'monthly_penalty_rate' => 'string',
            'legal_monthly_penalty_rate' => 'string',
            'monthly_reward_rate' => 'string',
            'late_penalty_grace_days' => 'integer',
            'version' => 'integer',
        ];
    }

    public function configured(): bool
    {
        return $this->monthly_penalty_rate !== null
            && $this->legal_monthly_penalty_rate !== null
            && $this->monthly_reward_rate !== null
            && $this->late_penalty_grace_days !== null;
    }

    /** @return array{monthly_penalty_rate:string,legal_monthly_penalty_rate:string,monthly_reward_rate:string,late_penalty_grace_days:string} */
    public function calculatorSettings(): array
    {
        if (! $this->configured()) {
            throw new \LogicException('Financial rules must be configured before creating settlement quotes.');
        }

        return [
            'monthly_penalty_rate' => (string) $this->monthly_penalty_rate,
            'legal_monthly_penalty_rate' => (string) $this->legal_monthly_penalty_rate,
            'monthly_reward_rate' => (string) $this->monthly_reward_rate,
            'late_penalty_grace_days' => (string) $this->late_penalty_grace_days,
        ];
    }

    /** @return array<string, string|int> */
    public function snapshot(): array
    {
        return $this->calculatorSettings() + ['version' => $this->version];
    }
}
