<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'periode' => 'required',
            'meteranSekarang' => 'required|integer|min:0',
            'fotoMeteran' => 'required|image|mimes:jpeg,png,jpg|max:10240',
            'jumlahPemakaian' => 'integer',
            'totalTagihan' => 'numeric',
        ];
    }
}
