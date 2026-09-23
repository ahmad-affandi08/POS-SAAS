<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

use App\Domain\Organisasi\Data\DataGudang;
use App\Domain\Organisasi\Enum\JenisGudang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SimpanGudangPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:150'],
            'Kode' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]{1,19}$/'],
            'Jenis' => ['required', Rule::enum(JenisGudang::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Kode.regex' => 'Kode 2–20 karakter: huruf, angka, atau tanda hubung. Misal: JKT1-DPR.'];
    }

    public function AmbilData(): DataGudang
    {
        return new DataGudang(
            nama: $this->string('Nama')->toString(),
            kode: $this->string('Kode')->toString(),
            jenis: JenisGudang::from($this->string('Jenis')->toString()),
        );
    }
}
