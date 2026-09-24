<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Data\DataSatuan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form satuan F-03 (E.5): `{ Nama, Simbol, BolehDesimal }`.
 */
final class SimpanSatuanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:100'],
            'Simbol' => ['required', 'string', 'max:20'],
            'BolehDesimal' => ['required', 'boolean'],
        ];
    }

    public function AmbilData(): DataSatuan
    {
        return new DataSatuan($this->string('Nama')->toString(), $this->string('Simbol')->toString(), $this->boolean('BolehDesimal'));
    }
}
