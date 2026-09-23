<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tindakan pengelola pada tenant yang cukup berisi alasan/keputusan tertulis (P-07, BR-P07.3): cabut override,
 * aktifkan kembali.
 */
final class AlasanTindakanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['Alasan' => ['required', 'string', 'min:10', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Alasan.min' => 'Tulis alasan minimal 10 karakter agar tim lain paham keputusannya.'];
    }

    public function AmbilAlasan(): string
    {
        return trim($this->string('Alasan')->toString());
    }
}
