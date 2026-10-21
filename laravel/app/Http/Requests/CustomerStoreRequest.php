<?php

namespace App\Http\Requests;

use App\Support\PersianDigits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['mobile', 'national_id'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = PersianDigits::toEnglish($this->input($field));
            }
        }

        $this->merge($values);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:190'],
            'mobile' => ['required', 'regex:/^09\d{9}$/', Rule::unique('customers', 'mobile')],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'national_id' => ['nullable', 'regex:/^\d{10}$/'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'نام و نام خانوادگی را وارد کنید.',
            'full_name.min' => 'نام باید دست‌کم دو نویسه باشد.',
            'mobile.required' => 'شمارهٔ همراه را وارد کنید.',
            'mobile.regex' => 'شمارهٔ همراه باید با قالب ۰۹۱۲۳۴۵۶۷۸۹ وارد شود.',
            'mobile.unique' => 'این شمارهٔ همراه قبلاً ثبت شده است.',
            'email.email' => 'نشانی ایمیل معتبر نیست.',
            'national_id.regex' => 'کد ملی باید ۱۰ رقم باشد.',
            'birth_date.date_format' => 'تاریخ تولد را به قالب میلادی سال-ماه-روز وارد کنید.',
            'birth_date.before_or_equal' => 'تاریخ تولد نمی‌تواند در آینده باشد.',
        ];
    }
}
