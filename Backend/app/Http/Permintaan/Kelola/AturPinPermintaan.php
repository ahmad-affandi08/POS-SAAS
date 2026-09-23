<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PIN kasir 6 digit + konfirmasi (F-02 langkah 4). Kekuatan PIN diperiksa `PenjagaPin` di Aksi.
 */
final class AturPinPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Pin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'KonfirmasiPin' => ['required', 'same:Pin'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Pin.required' => 'Isi PIN 6 angka.',
            'Pin.regex' => 'PIN harus 6 angka.',
            'KonfirmasiPin.required' => 'Ulangi PIN untuk konfirmasi.',
            'KonfirmasiPin.same' => 'Konfirmasi PIN tidak sama.',
        ];
    }
}
