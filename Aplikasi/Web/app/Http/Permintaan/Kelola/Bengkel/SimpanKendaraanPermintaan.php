<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Bengkel;

use App\Domain\Bengkel\Data\DataKendaraan;
use App\Http\Permintaan\Kelola\Grosir\AturanGrosir;
use Illuminate\Foundation\Http\FormRequest;

/** Isian kendaraan pelanggan bengkel (§9.10). Nomor polisi dinormalisasi & diperiksa keunikannya di Aksi. */
final class SimpanKendaraanPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPelanggan' => ['required', 'string', 'ulid'],
            'NomorPolisi' => ['required', 'string', 'max:20'],
            'Merek' => ['required', 'string', 'max:50'],
            'Tipe' => ['nullable', 'string', 'max:80'],
            'Tahun' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'Warna' => ['nullable', 'string', 'max:30'],
            'NomorRangka' => ['nullable', 'string', 'max:40'],
            'NomorMesin' => ['nullable', 'string', 'max:40'],
            'KmTerakhir' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'Catatan' => ['nullable', 'string', 'max:255'],
            'Aktif' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'UuidPelanggan' => 'pelanggan',
            'NomorPolisi' => 'nomor polisi',
            'Merek' => 'merek',
            'Tahun' => 'tahun',
            'KmTerakhir' => 'KM terakhir',
        ];
    }

    public function AmbilData(): DataKendaraan
    {
        $tahun = $this->validated('Tahun');
        $km = $this->validated('KmTerakhir');

        return new DataKendaraan(
            uuidPelanggan: strtoupper((string) $this->validated('UuidPelanggan')),
            nomorPolisi: (string) $this->validated('NomorPolisi'),
            merek: (string) $this->validated('Merek'),
            tipe: AturanGrosir::Teks($this->validated('Tipe')),
            tahun: is_numeric($tahun) ? (int) $tahun : null,
            warna: AturanGrosir::Teks($this->validated('Warna')),
            nomorRangka: AturanGrosir::Teks($this->validated('NomorRangka')),
            nomorMesin: AturanGrosir::Teks($this->validated('NomorMesin')),
            kmTerakhir: is_numeric($km) ? (int) $km : null,
            catatan: AturanGrosir::Teks($this->validated('Catatan')),
            aktif: $this->has('Aktif') ? $this->boolean('Aktif') : true,
        );
    }
}
