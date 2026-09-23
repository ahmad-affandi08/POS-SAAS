<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Data\DataWilayah;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SimpanWilayahPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Kode' => ['required', 'string', 'max:13'],
            'Nama' => ['required', 'string', 'max:150'],
            'Tingkat' => ['required', Rule::enum(TingkatWilayah::class)],
            'KodeInduk' => ['nullable', 'string', 'max:13'],
            'ZonaWaktu' => ['required', Rule::enum(ZonaWaktu::class)],
        ];
    }

    public function AmbilData(): DataWilayah
    {
        return new DataWilayah(
            kode: trim($this->string('Kode')->toString()),
            nama: trim($this->string('Nama')->toString()),
            tingkat: TingkatWilayah::from($this->string('Tingkat')->toString()),
            kodeInduk: $this->filled('KodeInduk') ? trim($this->string('KodeInduk')->toString()) : null,
            zonaWaktu: ZonaWaktu::from($this->string('ZonaWaktu')->toString()),
        );
    }
}
