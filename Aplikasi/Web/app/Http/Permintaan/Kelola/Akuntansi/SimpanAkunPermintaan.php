<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Akuntansi;

use App\Domain\Akuntansi\Data\DataAkun;
use App\Domain\Akuntansi\Enum\TipeAkun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Isian tambah/ubah akun bagan akun (F-13a). Kode `1-1100`: digit tipe, tanda hubung, 3–8 angka (aturan digit = tipe
 * di `AturanAkun`).
 */
final class SimpanAkunPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'regex:/^[1-6]-\d{3,8}$/'],
            'Nama' => ['required', 'string', 'max:100'],
            'Jenis' => ['required', 'string', Rule::enum(TipeAkun::class)],
            'Kontra' => ['sometimes', 'boolean'],
            'KasBank' => ['sometimes', 'boolean'],
            'UuidInduk' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Kode.regex' => 'Kode akun berformat seperti 1-1100: digit tipe (1–6), tanda hubung, lalu 3–8 angka.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['Kode' => 'kode akun', 'Nama' => 'nama akun', 'Jenis' => 'tipe akun', 'Kontra' => 'akun kontra', 'KasBank' => 'akun kas/bank', 'UuidInduk' => 'akun induk'];
    }

    public function AmbilData(): DataAkun
    {
        $induk = $this->validated('UuidInduk');

        return new DataAkun(
            (string) $this->validated('Kode'),
            (string) $this->validated('Nama'),
            TipeAkun::from((string) $this->validated('Jenis')),
            $this->boolean('Kontra'),
            $this->boolean('KasBank'),
            is_string($induk) && $induk !== '' ? $induk : null,
        );
    }
}
