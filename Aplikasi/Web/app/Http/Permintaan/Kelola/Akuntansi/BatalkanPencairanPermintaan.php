<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Akuntansi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alasan pembatalan pencairan (F-08, BR-08.4). Wajib diisi karena pembatalannya menarik kembali uang dari akun
 * kas/bank di buku; tanpa alasan tertulis, pembatalan seperti itu tidak bisa ditelusuri.
 */
final class BatalkanPencairanPermintaan extends FormRequest
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
    public function attributes(): array
    {
        return ['Alasan' => 'alasan'];
    }

    public function AmbilAlasan(): string
    {
        return (string) $this->validated('Alasan');
    }
}
