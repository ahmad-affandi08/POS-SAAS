<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Kasir;

use App\Domain\Kasir\Data\DataKategoriKas;
use App\Domain\Kasir\Enum\JenisKategoriKas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SimpanKategoriKasPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:100'],
            'Jenis' => ['required', 'string', Rule::enum(JenisKategoriKas::class)],
            'UuidAkun' => ['required', 'string', 'ulid'],
            'Urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Nama' => 'nama kategori', 'Jenis' => 'jenis', 'UuidAkun' => 'akun', 'Urutan' => 'urutan'];
    }

    public function AmbilData(): DataKategoriKas
    {
        return new DataKategoriKas(
            (string) $this->validated('Nama'),
            JenisKategoriKas::from((string) $this->validated('Jenis')),
            (string) $this->validated('UuidAkun'),
            (int) ($this->validated('Urutan') ?? 0),
        );
    }
}
