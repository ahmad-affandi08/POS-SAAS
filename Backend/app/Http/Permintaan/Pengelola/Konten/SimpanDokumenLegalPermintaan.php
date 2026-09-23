<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Konten;

use App\Domain\Pengelola\Konten\Data\DataDokumenLegal;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SimpanDokumenLegalPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Jenis' => ['required', 'string', Rule::enum(JenisDokumenLegal::class)],
            'Judul' => ['required', 'string', 'max:150'],
            'Isi' => ['required', 'string', 'max:200000'],
            'RingkasanPerubahan' => ['nullable', 'string', 'max:1000'],
            'Materiil' => ['required', 'boolean'],
            'BerlakuMulai' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function AmbilData(): DataDokumenLegal
    {
        return new DataDokumenLegal(
            jenis: JenisDokumenLegal::from($this->string('Jenis')->toString()),
            judul: trim($this->string('Judul')->toString()),
            isi: str_replace("\r\n", "\n", $this->string('Isi')->toString()),
            ringkasanPerubahan: $this->filled('RingkasanPerubahan') ? trim($this->string('RingkasanPerubahan')->toString()) : null,
            materiil: $this->boolean('Materiil'),
            berlakuMulai: $this->string('BerlakuMulai')->toString(),
        );
    }
}
