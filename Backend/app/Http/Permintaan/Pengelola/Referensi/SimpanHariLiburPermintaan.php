<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Pengelola\Referensi;

use App\Domain\Pengelola\Referensi\Data\DataHariLibur;
use App\Domain\Referensi\Enum\JenisHariLibur;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SimpanHariLiburPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'Nama' => ['required', 'string', 'max:150'],
            'Jenis' => ['required', Rule::enum(JenisHariLibur::class)],
            'NomorDasarHukum' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function AmbilData(): DataHariLibur
    {
        return new DataHariLibur(
            tanggal: CarbonImmutable::createFromFormat('!Y-m-d', $this->string('Tanggal')->toString()) ?: CarbonImmutable::today(),
            nama: trim($this->string('Nama')->toString()),
            jenis: JenisHariLibur::from($this->string('Jenis')->toString()),
            nomorDasarHukum: $this->filled('NomorDasarHukum') ? trim($this->string('NomorDasarHukum')->toString()) : null,
        );
    }
}
