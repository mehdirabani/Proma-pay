<?php

namespace App\Http\Requests;

use App\Support\PersianDigits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettlementQuoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('request_id') && $this->header('Idempotency-Key')) {
            $this->merge(['request_id' => $this->header('Idempotency-Key')]);
        }

        foreach (['amount_toman'] as $field) {
            if (is_string($this->input($field))) {
                $value = PersianDigits::toEnglish($this->input($field));
                $this->merge([$field => str_replace([',', '،', '٬', ' '], '', $value)]);
            }
        }

        if (is_array($this->input('installment_ids'))) {
            $this->merge(['installment_ids' => array_map(
                static fn (mixed $id): mixed => is_string($id) ? PersianDigits::toEnglish(trim($id)) : $id,
                $this->input('installment_ids'),
            )]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'amount_toman' => ['required', 'regex:/^[1-9]\d{0,17}$/'],
            'scope' => ['required', Rule::in(['selected', 'schedule', 'forward'])],
            'installment_ids' => ['nullable', 'array', 'max:600'],
            'installment_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('scope') === 'schedule') {
                return;
            }

            if (empty($this->input('installment_ids'))) {
                $validator->errors()->add('installment_ids', 'یک یا چند قسط را برای محاسبه انتخاب کنید.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسهٔ محاسبهٔ تسویه لازم است.',
            'request_id.uuid' => 'شناسهٔ محاسبه معتبر نیست.',
            'amount_toman.required' => 'مبلغی را که دریافت می‌شود وارد کنید.',
            'amount_toman.regex' => 'مبلغ باید عدد صحیح و مثبت به تومان باشد.',
            'scope.in' => 'دامنهٔ تسویه معتبر نیست.',
            'installment_ids.array' => 'فهرست اقساط معتبر نیست.',
            'installment_ids.max' => 'حداکثر ۶۰۰ قسط را هم‌زمان انتخاب کنید.',
            'installment_ids.*.integer' => 'شناسهٔ قسط معتبر نیست.',
            'installment_ids.*.distinct' => 'یک قسط را بیش از یک‌بار انتخاب نکنید.',
        ];
    }
}
