<?php

namespace App\Http\Requests;

use App\Support\PersianDigits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractDraftRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['principal_toman', 'down_payment_toman', 'installment_count'] as $field) {
            if (is_string($this->input($field))) {
                $normalized = PersianDigits::toEnglish($this->input($field));
                $values[$field] = str_replace(['تومان', 'ریال', ',', '،', '٬', ' '], '', $normalized);
            }
        }

        if (is_string($this->input('monthly_interest_rate'))) {
            $rate = PersianDigits::toEnglish($this->input('monthly_interest_rate'));
            $values['monthly_interest_rate'] = str_replace(['درصد', '٪', '%', ',', '،', '٬', ' '], '', $rate);
            $values['monthly_interest_rate'] = str_replace('٫', '.', $values['monthly_interest_rate']);
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'principal_toman' => ['required', 'regex:/^[1-9]\d{0,17}$/'],
            'down_payment_toman' => ['required', 'regex:/^(0|[1-9]\d{0,17})$/'],
            'monthly_interest_rate' => ['required', 'regex:/^(?:100(?:\.0{1,4})?|(?:\d|[1-9]\d)(?:\.\d{1,4})?)$/'],
            'interest_type' => ['required', Rule::in(['simple', 'compound'])],
            'installment_count' => ['required', 'integer', 'between:1,600'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'first_due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'request_uuid' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'مشتری قرارداد را مشخص کنید.',
            'principal_toman.required' => 'مبلغ اصل قرارداد را وارد کنید.',
            'principal_toman.regex' => 'اصل قرارداد باید مبلغ صحیح و مثبت به تومان باشد.',
            'down_payment_toman.required' => 'مبلغ پیش‌پرداخت را وارد کنید.',
            'down_payment_toman.regex' => 'پیش‌پرداخت باید مبلغ صحیح و نامنفی به تومان باشد.',
            'monthly_interest_rate.required' => 'نرخ سود ماهانه را وارد کنید.',
            'monthly_interest_rate.regex' => 'نرخ سود باید بین صفر تا ۱۰۰ درصد و حداکثر چهار رقم اعشار باشد.',
            'interest_type.in' => 'نوع محاسبهٔ سود معتبر نیست.',
            'installment_count.between' => 'تعداد اقساط باید بین ۱ تا ۶۰۰ باشد.',
            'start_date.date_format' => 'تاریخ قرارداد معتبر نیست.',
            'first_due_date.date_format' => 'تاریخ اولین سررسید معتبر نیست.',
            'first_due_date.after_or_equal' => 'اولین سررسید نمی‌تواند قبل از تاریخ قرارداد باشد.',
            'request_uuid.uuid' => 'شناسهٔ ثبت قرارداد معتبر نیست.',
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $principal = ltrim((string) $this->input('principal_toman'), '0');
            $downPayment = ltrim((string) $this->input('down_payment_toman'), '0');
            $principal = $principal === '' ? '0' : $principal;
            $downPayment = $downPayment === '' ? '0' : $downPayment;

            if (strlen($downPayment) > strlen($principal)
                || (strlen($downPayment) === strlen($principal) && strcmp($downPayment, $principal) > 0)) {
                $validator->errors()->add('down_payment_toman', 'پیش‌پرداخت نمی‌تواند بیشتر از اصل قرارداد باشد.');
            }
        }];
    }
}
