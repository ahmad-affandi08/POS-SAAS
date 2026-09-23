<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Data\DataReferensiBank;
use App\Domain\Referensi\Enum\JenisReferensiBank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SimpanReferensiBankPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'regex:/^[A-Z0-9_-]{2,30}$/'],
            'Nama' => ['required', 'string', 'max:150'],
            'Jenis' => ['required', Rule::enum(JenisReferensiBank::class)],
            'Aktif' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Kode.regex' => 'Kode hanya huruf besar, angka, garis bawah, atau tanda hubung (2–30 karakter).'];
    }

    public function AmbilData(): DataReferensiBank
    {
        return new DataReferensiBank(
            kode: $this->string('Kode')->toString(),
            nama: trim($this->string('Nama')->toString()),
            jenis: JenisReferensiBank::from($this->string('Jenis')->toString()),
            aktif: $this->boolean('Aktif'),
        );
    }
}
