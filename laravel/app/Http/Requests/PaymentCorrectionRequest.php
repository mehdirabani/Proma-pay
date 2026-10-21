<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('payments.correct') ?? false;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
