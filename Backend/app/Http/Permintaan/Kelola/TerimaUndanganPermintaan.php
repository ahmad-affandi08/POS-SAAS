<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Organisasi\Data\DataAkunBaru;
use App\Http\Permintaan\Autentikasi\DaftarPermintaan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Menerima undangan: tanpa isian bila sudah masuk (akun ditautkan), selain itu data akun baru.
 */
final class TerimaUndanganPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        if ($this->user('web') !== null) {
            return [];
        }

        return [
            'Nama' => ['required', 'string', 'max:150'],
            'NoHp' => ['nullable', 'string', 'regex:'.DaftarPermintaan::POLA_NO_HP],
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
            'NoHp.regex' => 'Nomor WhatsApp diawali 08 atau +628, misal 081234567890.',
            'KonfirmasiKataSandi.same' => 'Konfirmasi kata sandi tidak sama.',
        ];
    }

    public function AmbilAkunBaru(): ?DataAkunBaru
    {
        if ($this->user('web') !== null) {
            return null;
        }

        return new DataAkunBaru(
            nama: trim($this->string('Nama')->toString()),
            noHp: $this->filled('NoHp') ? DaftarPermintaan::NormalkanNoHp($this->string('NoHp')->toString()) : null,
            kataSandi: $this->string('KataSandi')->toString(),
        );
    }
}
