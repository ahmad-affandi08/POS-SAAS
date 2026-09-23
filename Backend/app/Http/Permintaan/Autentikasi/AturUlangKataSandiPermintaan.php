<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Autentikasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AturUlangKataSandiPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Token' => ['required', 'string', 'max:200'],
            'Email' => ['required', 'string', 'email:rfc', 'max:191'],
            // Aturan kekuatan sama dengan registrasi (DaftarPermintaan).
            'KataSandi' => ['required', 'string', Password::min(8)->letters()->numbers(), 'max:100'],
            'KonfirmasiKataSandi' => ['required', 'same:KataSandi'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'KonfirmasiKataSandi.same' => 'Konfirmasi kata sandi tidak sama.',
        ];
    }
}
