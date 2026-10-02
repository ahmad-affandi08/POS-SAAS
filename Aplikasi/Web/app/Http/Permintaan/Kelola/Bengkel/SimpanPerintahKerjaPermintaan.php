<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Bengkel;

use App\Domain\Bengkel\Data\DataBarisPerintahKerja;
use App\Domain\Bengkel\Data\DataPerintahKerja;
use App\Domain\Bengkel\Enum\JenisBarisPerintahKerja;
use App\Http\Permintaan\Kelola\Grosir\AturanGrosir;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Isian perintah kerja bengkel (§9.10). **Harga tidak ada di sini**: server mengambilnya dari price engine (tier
 * pelanggan) saat menyimpan, jadi harga estimasi tidak bisa dikarang dari peramban. Outlet diubah ke Id oleh kontroler
 * (batas outlet pelaku). Estimasi selesai `TTTT-BB-HHTjj:mm` (keluaran `PemilihTanggalWaktu`) dalam zona outlet.
 */
final class SimpanPerintahKerjaPermintaan extends FormRequest
{
    public const MAKS_BARIS = 100;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidOutlet' => ['required', 'string', 'ulid'],
            'UuidPelanggan' => ['required', 'string', 'ulid'],
            'UuidKendaraan' => ['required', 'string', 'ulid'],
            'KmMasuk' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'Keluhan' => ['required', 'string', 'max:2000'],
            'Diagnosis' => ['nullable', 'string', 'max:2000'],
            'EstimasiSelesaiPada' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'Baris' => ['present', 'array', 'max:'.self::MAKS_BARIS],
            'Baris.*.Jenis' => ['required', Rule::enum(JenisBarisPerintahKerja::class)],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.UuidProdukSatuan' => ['nullable', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', AturanGrosir::JUMLAH],
            'Baris.*.Diskon' => ['nullable', 'string', AturanGrosir::UANG],
            'Baris.*.UuidKaryawan' => ['nullable', 'string', 'ulid'],
            'Baris.*.Catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AturanGrosir::Pesan();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'UuidOutlet' => 'outlet',
            'UuidPelanggan' => 'pelanggan',
            'UuidKendaraan' => 'kendaraan',
            'KmMasuk' => 'KM masuk',
            'Keluhan' => 'keluhan',
            'EstimasiSelesaiPada' => 'perkiraan selesai',
            'Baris.*.Jumlah' => 'jumlah',
            'Baris.*.Diskon' => 'diskon',
        ];
    }

    public function AmbilData(int $idOutlet, string $zonaWaktu): DataPerintahKerja
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));
        $estimasi = $this->validated('EstimasiSelesaiPada');
        $km = $this->validated('KmMasuk');

        return new DataPerintahKerja(
            idOutlet: $idOutlet,
            uuidPelanggan: strtoupper((string) $this->validated('UuidPelanggan')),
            uuidKendaraan: strtoupper((string) $this->validated('UuidKendaraan')),
            kmMasuk: is_numeric($km) ? (int) $km : null,
            keluhan: (string) $this->validated('Keluhan'),
            diagnosis: AturanGrosir::Teks($this->validated('Diagnosis')),
            estimasiSelesaiPada: is_string($estimasi) && $estimasi !== '' ? (CarbonImmutable::createFromFormat('Y-m-d\TH:i', $estimasi, $zonaWaktu) ?: null) : null,
            baris: array_map(fn (array $b): DataBarisPerintahKerja => new DataBarisPerintahKerja(
                JenisBarisPerintahKerja::from((string) $b['Jenis']),
                strtoupper((string) $b['UuidProduk']),
                isset($b['UuidProdukSatuan']) && is_string($b['UuidProdukSatuan']) && $b['UuidProdukSatuan'] !== '' ? strtoupper($b['UuidProdukSatuan']) : null,
                AturanGrosir::Jumlah($b['Jumlah']),
                AturanGrosir::Uang($b['Diskon'] ?? null),
                isset($b['UuidKaryawan']) && is_string($b['UuidKaryawan']) && $b['UuidKaryawan'] !== '' ? strtoupper($b['UuidKaryawan']) : null,
                AturanGrosir::Teks($b['Catatan'] ?? null),
            ), $baris),
        );
    }
}
