<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Autentikasi;

use Illuminate\Foundation\Http\FormRequest;

final class SetujuiDokumenLegalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Dokumen' => ['required', 'array', 'min:1', 'max:10'],
            'Dokumen.*' => ['required', 'string', 'size:26'],
            'Setuju' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Setuju.accepted' => 'Centang persetujuan untuk melanjutkan.',
        ];
    }

    /**
     * @return list<string>
     */
    public function AmbilUuidDokumen(): array
    {
        return array_values(array_map('strval', (array) $this->input('Dokumen', [])));
    }
}
