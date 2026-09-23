<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\PanduanAwal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F-01 langkah 6: perangkat kasir di outlet wizard (jenis selalu Kasir). Batas perangkat per outlet (BR-02.1) diperiksa
 * Aksi `BuatPerangkat` (F-02b).
 */
final class SimpanPerangkatPanduanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Nama.required' => 'Isi nama perangkat, misal Kasir Depan.'];
    }
}
