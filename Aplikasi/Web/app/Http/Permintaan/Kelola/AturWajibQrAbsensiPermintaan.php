<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wajib QR absensi outlet (F-18 bagian 4, D-37): `{ Wajib }`.
 */
final class AturWajibQrAbsensiPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['Wajib' => ['required', 'boolean']];
    }
}
