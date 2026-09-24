<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Persediaan\Data\DataBarisStokAwal;
use App\Domain\Persediaan\Data\DataStokAwal;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form stok awal POST (buat, `Uuid` klien opsional untuk idempotensi) dan PUT (ubah draf, wajib
 * `VersiDiubahPada`) (DesainF05a D). Jumlah dalam satuan dasar (maks 4 desimal), HPP per satuan dasar (maks 6
 * desimal), keduanya string desimal tanpa titik ribuan. Aturan bisnis (produk, pelacakan, tanggal) diperiksa Aksi.
 */
final class SimpanStokAwalPermintaan extends FormRequest
{
    public const POLA_JUMLAH = 'regex:/^\d{1,14}(\.\d{1,4})?$/';

    public const POLA_HPP = 'regex:/^\d{1,13}(\.\d{1,6})?$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $maksimalBaris = (int) config('persediaan.StokAwal.MaksimalBaris', 2000);
        $maksimalSeri = (int) config('persediaan.StokAwal.MaksimalNomorSeriPerBaris', 1000);
        $buat = $this->isMethod('post');

        return [
            ...($buat ? ['Uuid' => ['nullable', 'ulid']] : ['VersiDiubahPada' => ['required', 'string', 'max:40']]),
            'UuidGudang' => ['required', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'Baris' => ['required', 'array', 'min:1', "max:{$maksimalBaris}"],
            'Baris.*.UuidProduk' => ['required', 'ulid'],
            'Baris.*.Jumlah' => ['required', self::POLA_JUMLAH],
            'Baris.*.HppSatuan' => ['required', self::POLA_HPP],
            'Baris.*.NomorBatch' => ['nullable', 'string', 'max:60'],
            'Baris.*.TanggalKedaluwarsa' => ['nullable', 'date_format:Y-m-d'],
            'Baris.*.NomorSeri' => ['nullable', 'array', "max:{$maksimalSeri}"],
            'Baris.*.NomorSeri.*' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Baris.required' => 'Tambahkan minimal satu produk.',
            'Baris.min' => 'Tambahkan minimal satu produk.',
            'Baris.max' => 'Satu dokumen stok awal maksimal :max baris. Pecah menjadi beberapa dokumen.',
            'Baris.*.Jumlah.regex' => 'Stok berupa angka tanpa titik ribuan, maksimal 4 angka desimal. Misal: 12 atau 2.5.',
            'Baris.*.HppSatuan.regex' => 'Harga modal berupa angka tanpa titik ribuan, maksimal 6 angka desimal. Misal: 15000 atau 1234.5678.',
            'Baris.*.NomorSeri.max' => 'Satu baris maksimal :max nomor seri.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'UuidGudang' => 'lokasi stok',
            'Tanggal' => 'tanggal',
            'Catatan' => 'catatan',
            'Baris' => 'baris produk',
            'Baris.*.UuidProduk' => 'produk',
            'Baris.*.Jumlah' => 'stok',
            'Baris.*.HppSatuan' => 'harga modal',
            'Baris.*.NomorBatch' => 'nomor batch',
            'Baris.*.TanggalKedaluwarsa' => 'tanggal kedaluwarsa',
            'Baris.*.NomorSeri' => 'nomor seri',
            'Baris.*.NomorSeri.*' => 'nomor seri',
        ];
    }

    /**
     * Masukan Aksi. `idGudang` sudah diperiksa kontroler (lokasi stok di outlet yang boleh diakses). Produk yang tidak
     * dikenal di tenant aktif dipetakan ke Id 0 sehingga Aksi menolaknya per baris (`ProdukTidakDikenal`).
     */
    public function AmbilData(int $idGudang): DataStokAwal
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));
        $uuidProduk = array_values(array_map(fn (array $b): string => (string) $b['UuidProduk'], $baris));
        $produk = app(InfoProdukStok::class)->AmbilDariUuid($uuidProduk);

        return new DataStokAwal(
            uuid: $this->isMethod('post') && is_string($this->validated('Uuid')) ? (string) $this->validated('Uuid') : null,
            idGudang: $idGudang,
            tanggal: CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->validated('Tanggal')) ?: CarbonImmutable::today(),
            catatan: is_string($this->validated('Catatan')) ? (string) $this->validated('Catatan') : null,
            baris: array_map(fn (array $b): DataBarisStokAwal => new DataBarisStokAwal(
                idProduk: ($produk[(string) $b['UuidProduk']] ?? null)->id ?? 0,
                jumlah: Kuantitas::Dari((string) $b['Jumlah']),
                hppSatuan: BigDecimal::of((string) $b['HppSatuan']),
                nomorBatch: is_string($b['NomorBatch'] ?? null) ? $b['NomorBatch'] : null,
                tanggalKedaluwarsa: is_string($b['TanggalKedaluwarsa'] ?? null) ? (CarbonImmutable::createFromFormat('!Y-m-d', $b['TanggalKedaluwarsa']) ?: null) : null,
                nomorSeri: array_values(array_map('strval', is_array($b['NomorSeri'] ?? null) ? $b['NomorSeri'] : [])),
            ), $baris),
            versiDiubahPada: $this->isMethod('put') && is_string($this->validated('VersiDiubahPada')) ? (string) $this->validated('VersiDiubahPada') : null,
        );
    }
}
