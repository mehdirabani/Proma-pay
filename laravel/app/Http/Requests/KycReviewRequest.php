<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KycReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['review_note.required_if' => 'برای رد احراز هویت، درج دلیل الزامی است.'];
    }
}
