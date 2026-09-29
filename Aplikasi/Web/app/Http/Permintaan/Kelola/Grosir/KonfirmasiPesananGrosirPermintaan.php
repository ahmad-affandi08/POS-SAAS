<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Grosir;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Konfirmasi SO grosir (BR-12.6). Alasan persetujuan kredit hanya wajib bila paparannya melewati limit — yang
 * memutuskan itu Aksinya (`AlasanPersetujuanWajib`), bukan formulir ini, supaya aturannya satu tempat saja.
 */
final class KonfirmasiPesananGrosirPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['AlasanPersetujuanKredit' => ['nullable', 'string', 'min:5', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['AlasanPersetujuanKredit' => 'alasan persetujuan kredit'];
    }

    public function AmbilAlasan(): ?string
    {
        return AturanGrosir::Teks($this->validated('AlasanPersetujuanKredit'));
    }
}
