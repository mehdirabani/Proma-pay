<?php

namespace App\Http\Requests;

use App\Support\PersianDigits;
use Illuminate\Foundation\Http\FormRequest;

class FinancialRuleUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['monthly_penalty_rate', 'legal_monthly_penalty_rate', 'monthly_reward_rate', 'late_penalty_grace_days'] as $key) {
            if (is_string($this->input($key))) {
                $value = PersianDigits::toEnglish(trim($this->input($key)));
                $value = str_replace(['٫', '٪', '%', ' '], ['.', '', '', ''], $value);
                $this->merge([$key => $value]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rate = ['required', 'regex:/^(?:100(?:\.0{1,4})?|\d{1,2}(?:\.\d{1,4})?)$/'];

        return [
            'request_id' => ['required', 'uuid'],
            'monthly_penalty_rate' => $rate,
            'legal_monthly_penalty_rate' => $rate,
            'monthly_reward_rate' => $rate,
            'late_penalty_grace_days' => ['required', 'integer', 'between:0,365'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسهٔ یکتای درخواست تنظیمات لازم است.',
            'request_id.uuid' => 'شناسهٔ درخواست معتبر نیست.',
            'monthly_penalty_rate.required' => 'نرخ جریمهٔ عادی را وارد کنید.',
            'monthly_penalty_rate.regex' => 'نرخ باید بین صفر تا ۱۰۰ و با حداکثر چهار رقم اعشار باشد.',
            'legal_monthly_penalty_rate.required' => 'نرخ جریمهٔ حقوقی را وارد کنید.',
            'legal_monthly_penalty_rate.regex' => 'نرخ باید بین صفر تا ۱۰۰ و با حداکثر چهار رقم اعشار باشد.',
            'monthly_reward_rate.required' => 'نرخ پاداش تسویهٔ زودهنگام را وارد کنید.',
            'monthly_reward_rate.regex' => 'نرخ باید بین صفر تا ۱۰۰ و با حداکثر چهار رقم اعشار باشد.',
            'late_penalty_grace_days.required' => 'مهلت ارفاق را به روز وارد کنید.',
            'late_penalty_grace_days.integer' => 'مهلت ارفاق باید عدد صحیح باشد.',
            'late_penalty_grace_days.between' => 'مهلت ارفاق باید بین صفر تا ۳۶۵ روز باشد.',
        ];
    }
}
