<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Support\PersianDigits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentIntentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key') && $this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }

        if (is_string($this->input('amount_toman'))) {
            $normalized = PersianDigits::toEnglish($this->input('amount_toman'));
            $normalized = str_replace([',', '٬', ' '], '', $normalized);
            $this->merge(['amount_toman' => $normalized]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'idempotency_key' => ['required', 'uuid'],
            'amount_toman' => ['required', 'regex:/^[1-9]\d{0,17}$/'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'provider_reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
