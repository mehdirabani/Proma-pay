<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractTransitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
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
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'شناسهٔ یکتای این اقدام الزامی است.',
            'request_id.uuid' => 'شناسهٔ این اقدام معتبر نیست.',
            'reason.required' => 'دلیل این تغییر وضعیت را وارد کنید.',
            'reason.min' => 'دلیل باید دست‌کم پنج نویسه باشد.',
            'reason.max' => 'دلیل نمی‌تواند بیش از ۵۰۰ نویسه باشد.',
        ];
    }
}
