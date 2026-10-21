<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualPaymentCaptureRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key') && $this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }

        if (is_string($this->input('provider_reference'))) {
            $this->merge(['provider_reference' => trim($this->input('provider_reference'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'method' => ['required', Rule::in(['cash', 'card', 'pos', 'bank_transfer', 'manual'])],
            'provider_reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'شناسهٔ یکتای ثبت پرداخت لازم است.',
            'idempotency_key.uuid' => 'شناسهٔ ثبت پرداخت معتبر نیست.',
            'method.in' => 'در این نسخه فقط ثبت حضوری، نقدی، کارت، کارت‌خوان و انتقال بانکی پشتیبانی می‌شود.',
            'provider_reference.max' => 'شمارهٔ پیگیری نمی‌تواند بیش از ۱۲۰ نویسه باشد.',
        ];
    }
}
