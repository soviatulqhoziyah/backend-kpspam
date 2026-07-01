<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
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
            'namaPengeluaran'  => 'required|string|max:100',
            'nominal'          => 'required|numeric',
            'foto_bukti_base64' => 'required|string',
            'foto_bukti_ext'   => 'required|string|in:jpg,jpeg,png',
        ];
    }
}
