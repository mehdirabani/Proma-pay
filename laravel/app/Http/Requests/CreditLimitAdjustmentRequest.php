<?php

namespace App\Http\Requests;

use App\Support\PersianDigits;
use Illuminate\Foundation\Http\FormRequest;

class CreditLimitAdjustmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('new_limit_toman'))) {
            $normalized = PersianDigits::toEnglish($this->input('new_limit_toman'));
            $normalized = str_replace([',', '٬', '،', ' '], '', $normalized);
            $this->merge(['new_limit_toman' => $normalized]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_limit_toman' => ['required', 'regex:/^(0|[1-9]\d{0,17})$/'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'request_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_limit_toman.required' => 'سقف اعتبار جدید را وارد کنید.',
            'new_limit_toman.regex' => 'مبلغ باید یک عدد صحیح تومان باشد.',
            'reason.required' => 'دلیل تغییر سقف اعتبار الزامی است.',
            'reason.min' => 'دلیل باید دست‌کم پنج نویسه داشته باشد.',
            'request_id.uuid' => 'شناسهٔ درخواست معتبر نیست.',
        ];
    }
}
