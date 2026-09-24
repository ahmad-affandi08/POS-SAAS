<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi alasan pembatalan stok awal: wajib, 5–255 karakter (DesainF05a D). Alasan disimpan di dokumen, riwayat
 * status, dan LogAudit.
 */
final class BatalkanStokAwalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Alasan' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Alasan.required' => 'Tulis alasan pembatalan.',
            'Alasan.min' => 'Alasan pembatalan minimal :min karakter.',
            'Alasan.max' => 'Alasan pembatalan maksimal :max karakter.',
        ];
    }

    public function AmbilAlasan(): string
    {
        return trim((string) $this->validated('Alasan'));
    }
}
