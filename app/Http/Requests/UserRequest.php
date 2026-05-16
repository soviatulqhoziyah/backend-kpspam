<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
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
        // Ambil ID user dari URL (untuk pengecekan unique)
        $userId = $this->route('id');

        return [
            // Jika POST (Store), username wajib unik. 
            // Jika PUT (Update), username unik tapi abaikan ID user saat ini.
            'username' => 'required|unique:users,username,' . $userId,

            // Jika POST (Store), password wajib. 
            // Jika PUT (Update), password boleh kosong (nullable).
            'password' => $this->isMethod('post') ? 'required|min:6' : 'nullable|min:6',

            'role' => 'required|in:admin,pelanggan,petugas',
            'alamat' => 'required|in:talbar,taltim',
            'noTelepon' => 'required',
            'namaLengkap' => 'required',
        ];
    }
}
