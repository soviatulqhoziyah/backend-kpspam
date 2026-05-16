<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'billing_id' => 'required|exists:billings,id',
            'metodePembayaran' => 'required|in:tunai,non_tunai',
            'nominalPembayaran' => 'required|numeric|min:0',
        ];
    }
}
