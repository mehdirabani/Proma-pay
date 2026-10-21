<?php

namespace App\Http\Requests;

use App\Support\PersianDigits;
use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentReceiptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('amount_toman'))) {
            $amount = PersianDigits::toEnglish($this->input('amount_toman'));
            $this->merge(['amount_toman' => str_replace([',', '،', '٬', ' '], '', $amount)]);
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
            'receipt' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'extensions:jpg,jpeg,png,webp,pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسهٔ یکتای ثبت مدرک لازم است.',
            'request_id.uuid' => 'شناسهٔ ثبت مدرک معتبر نیست.',
            'amount_toman.required' => 'مبلغ انتقال بانکی را وارد کنید.',
            'amount_toman.regex' => 'مبلغ باید عدد صحیح و مثبت به تومان باشد.',
            'receipt.required' => 'تصویر یا فایل مدرک انتقال را انتخاب کنید.',
            'receipt.file' => 'فایل انتخاب‌شده قابل خواندن نیست.',
            'receipt.max' => 'حجم فایل نباید بیشتر از ۵ مگابایت باشد.',
            'receipt.mimes' => 'فقط فایل JPG، PNG، WebP یا PDF پذیرفته می‌شود.',
            'receipt.mimetypes' => 'نوع واقعی فایل با قالب‌های مجاز رسید سازگار نیست.',
            'receipt.extensions' => 'پسوند فایل باید JPG، PNG، WebP یا PDF باشد.',
        ];
    }
}
