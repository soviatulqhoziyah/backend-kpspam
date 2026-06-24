<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComplaintRequest extends FormRequest
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
            'deskripsi' => 'required|min:10',
            'fotoBukti' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'kategori' => 'nullable|in:penyumbatan,kebocoran,air_keruh,lainnya',
        ];
    }
}
