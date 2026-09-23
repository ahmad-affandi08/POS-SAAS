<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Data\DataSatuanStandar;
use Illuminate\Foundation\Http\FormRequest;

final class SimpanSatuanStandarPermintaan extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'regex:/^[A-Z0-9_]{1,20}$/'],
            'Nama' => ['required', 'string', 'max:100'],
            'Simbol' => ['required', 'string', 'max:20'],
            'BolehDesimal' => ['required', 'boolean'],
            'Aktif' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Kode.regex' => 'Kode hanya huruf besar, angka, atau garis bawah (maksimal 20 karakter).'];
    }

    public function AmbilData(): DataSatuanStandar
    {
        return new DataSatuanStandar(
            kode: $this->string('Kode')->toString(),
            nama: trim($this->string('Nama')->toString()),
            simbol: trim($this->string('Simbol')->toString()),
            bolehDesimal: $this->boolean('BolehDesimal'),
            aktif: $this->boolean('Aktif'),
        );
    }
}
