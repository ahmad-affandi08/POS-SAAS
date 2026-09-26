<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * D-22: ganti kata sandi anggota tim (wajib setelah kata sandi awal dibuat Super Admin).
 */
final class GantiKataSandiPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'KataSandiLama' => ['required', 'string', 'max:255'],
            'KataSandi' => ['required', 'string', 'max:255', Password::min(12)->letters()->numbers()],
            'KonfirmasiKataSandi' => ['required', 'same:KataSandi'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['KonfirmasiKataSandi.same' => 'Konfirmasi kata sandi tidak sama.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['KataSandiLama' => 'kata sandi saat ini', 'KataSandi' => 'kata sandi baru'];
    }
}
