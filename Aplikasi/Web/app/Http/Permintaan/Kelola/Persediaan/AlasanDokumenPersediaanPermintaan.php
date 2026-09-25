<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi alasan tindakan dokumen persediaan F-05b (batal/tutup transfer, batal/kembalikan opname, tolak
 * penyesuaian): wajib, 5–255 karakter. Alasan disimpan di dokumen, riwayat status, dan LogAudit.
 */
final class AlasanDokumenPersediaanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['Alasan' => ['required', 'string', 'min:5', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Alasan.required' => 'Tulis alasannya.',
            'Alasan.min' => 'Alasan minimal :min karakter.',
            'Alasan.max' => 'Alasan maksimal :max karakter.',
        ];
    }

    public function AmbilAlasan(): string
    {
        return trim((string) $this->validated('Alasan'));
    }
}
