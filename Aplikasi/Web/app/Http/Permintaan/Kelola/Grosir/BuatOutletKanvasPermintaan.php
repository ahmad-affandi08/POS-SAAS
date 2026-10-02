<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Grosir;

use App\Http\Permintaan\Kelola\SimpanOutletPermintaan;
use Illuminate\Foundation\Http\FormRequest;

/** Modul Salesman bagian 3: "Tambah kendaraan kanvas" — cukup plat nomornya (misal "AD 1234 XY"). */
final class BuatOutletKanvasPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['NomorKendaraan' => ['required', 'string', 'max:20', 'regex:'.SimpanOutletPermintaan::POLA_NOMOR_KENDARAAN]];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'NomorKendaraan.required' => 'Isi nomor kendaraan, misal AD 1234 XY.',
            'NomorKendaraan.regex' => 'Nomor kendaraan hanya huruf, angka, dan spasi. Misal: AD 1234 XY.',
            'NomorKendaraan.max' => 'Nomor kendaraan paling banyak 20 karakter.',
        ];
    }

    public function AmbilNomorKendaraan(): string
    {
        return (string) $this->validated('NomorKendaraan');
    }
}
