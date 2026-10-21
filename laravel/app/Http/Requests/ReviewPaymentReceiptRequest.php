<?php

namespace App\Http\Requests;

use App\Support\PersianDigits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPaymentReceiptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('approved_amount_toman'))) {
            $amount = PersianDigits::toEnglish($this->input('approved_amount_toman'));
            $this->merge(['approved_amount_toman' => str_replace([',', '،', '٬', ' '], '', $amount)]);
        }
        if (is_string($this->input('review_note'))) {
            $this->merge(['review_note' => trim($this->input('review_note'))]);
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
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['required', 'string', 'min:5', 'max:1000'],
            'approved_amount_toman' => ['nullable', 'regex:/^[1-9]\d{0,17}$/'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->input('decision') === 'rejected' && $this->filled('approved_amount_toman')) {
                $validator->errors()->add('approved_amount_toman', 'برای رد مدرک، مبلغ تأییدشده وارد نمی‌شود.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسهٔ یکتای تصمیم لازم است.',
            'request_id.uuid' => 'شناسهٔ تصمیم معتبر نیست.',
            'decision.in' => 'نتیجهٔ بررسی معتبر نیست.',
            'review_note.required' => 'شرح نتیجهٔ بررسی را وارد کنید.',
            'review_note.min' => 'شرح نتیجه باید دست‌کم ۵ نویسه باشد.',
            'review_note.max' => 'شرح نتیجه نمی‌تواند بیش از ۱۰۰۰ نویسه باشد.',
            'approved_amount_toman.regex' => 'مبلغ تأییدشده باید عدد صحیح و مثبت به تومان باشد.',
        ];
    }
}
