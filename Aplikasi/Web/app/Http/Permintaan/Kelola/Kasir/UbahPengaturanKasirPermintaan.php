<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Kasir;

use Illuminate\Foundation\Http\FormRequest;

final class UbahPengaturanKasirPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'BatasKasKeluar' => ['required', 'string', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'ShiftBersama' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['BatasKasKeluar.regex' => 'Batas kas keluar harus berupa nominal rupiah, misal 200000.'];
    }
}
