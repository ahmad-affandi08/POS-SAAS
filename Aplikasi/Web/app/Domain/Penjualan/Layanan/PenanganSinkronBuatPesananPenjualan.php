<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Aksi\TerimaPesananPenjualanPos;
use App\Domain\Penjualan\Data\DataBarisPesananTerbuka;
use App\Domain\Penjualan\Data\DataPembayaranPenjualanPos;
use App\Domain\Penjualan\Data\DataPesananPenjualanPos;
use Carbon\CarbonImmutable;

/**
 * Item outbox `PesananPenjualan.Buat` (F-12 bagian 2, pre-order + uang muka). Bentuk `Data`: `{UuidShift, UuidPengguna,
 * UuidPelanggan, Nomor, DipesanPada, TanggalAmbil "YYYY-MM-DD", Catatan?, TotalPesanan, Baris [{Uuid, UuidProduk,
 * UuidProdukSatuan|null, Jumlah, HargaSatuan, HargaPilihan, Pilihan [{UuidPilihan, Nama, Harga}], Catatan}],
 * Pembayaran [{Uuid, UuidMetodePembayaran, Jumlah, Referensi|null}]}`. Uuid item = Uuid pesanan; uang & jumlah string
 * desimal. Aturan bisnis di `TerimaPesananPenjualanPos`.
 */
final class PenanganSinkronBuatPesananPenjualan implements PenanganItemSinkron
{
    public const JENIS = 'PesananPenjualan.Buat';

    private const POLA_JUMLAH = '/^\d{1,14}(\.\d{1,4})?$/';

    public function __construct(private readonly TerimaPesananPenjualanPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $uang = 'regex:'.ValidasiItemSinkron::POLA_UANG;
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidShift' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'UuidPelanggan' => ['required', 'string', 'ulid'],
            'Nomor' => ['required', 'string', 'max:80'],
            'DipesanPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'TanggalAmbil' => ['required', 'string', 'date_format:Y-m-d'],
            'Catatan' => ['sometimes', 'nullable', 'string', 'max:500'],
            'TotalPesanan' => ['required', 'string', $uang],
            'Baris' => ['required', 'array', 'min:1', 'max:200'],
            'Baris.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.UuidProdukSatuan' => ['nullable', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', 'regex:'.self::POLA_JUMLAH],
            'Baris.*.HargaSatuan' => ['required', 'string', $uang],
            'Baris.*.HargaPilihan' => ['sometimes', 'nullable', 'string', $uang],
            'Baris.*.Pilihan' => ['sometimes', 'nullable', 'array', 'max:30'],
            'Baris.*.Pilihan.*.UuidPilihan' => ['required', 'string', 'ulid'],
            'Baris.*.Pilihan.*.Nama' => ['required', 'string', 'max:100'],
            'Baris.*.Pilihan.*.Harga' => ['required', 'string', $uang],
            'Baris.*.Catatan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Pembayaran' => ['required', 'array', 'min:1', 'max:5'],
            'Pembayaran.*.Uuid' => ['required', 'string', 'ulid', 'distinct'],
            'Pembayaran.*.UuidMetodePembayaran' => ['required', 'string', 'ulid'],
            'Pembayaran.*.Jumlah' => ['required', 'string', $uang],
            'Pembayaran.*.Referensi' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $baris = [];

        foreach (array_values((array) $valid['Baris']) as $indeks => $b) {
            if (Kuantitas::Dari((string) $b['Jumlah'])->KeDesimal()->isZero()) {
                throw new PelanggaranAturanBisnis('DataTidakValid', 'Jumlah barang harus lebih dari 0.', "Baris.{$indeks}.Jumlah");
            }

            $baris[] = new DataBarisPesananTerbuka(
                uuid: strtoupper((string) $b['Uuid']),
                uuidProduk: strtoupper((string) $b['UuidProduk']),
                uuidProdukSatuan: is_string($b['UuidProdukSatuan'] ?? null) ? strtoupper($b['UuidProdukSatuan']) : null,
                jumlah: (string) $b['Jumlah'],
                hargaSatuan: (string) $b['HargaSatuan'],
                hargaPilihan: is_string($b['HargaPilihan'] ?? null) ? $b['HargaPilihan'] : '0',
                pilihan: array_values(array_map(fn (array $p): array => [
                    'UuidPilihan' => strtoupper((string) $p['UuidPilihan']),
                    'Nama' => (string) $p['Nama'],
                    'Harga' => Uang::Dari((string) $p['Harga'])->KeString(),
                ], is_array($b['Pilihan'] ?? null) ? $b['Pilihan'] : [])),
                catatan: self::AmbilTeks($b['Catatan'] ?? null),
            );
        }

        return $this->terima->Jalankan(new DataPesananPenjualanPos(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidShift: strtoupper((string) $valid['UuidShift']),
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            uuidPelanggan: strtoupper((string) $valid['UuidPelanggan']),
            nomor: trim((string) $valid['Nomor']),
            dipesanPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DipesanPada']),
            tanggalAmbil: CarbonImmutable::createFromFormat('Y-m-d', (string) $valid['TanggalAmbil'], 'UTC')?->startOfDay() ?? CarbonImmutable::now(),
            catatan: self::AmbilTeks($valid['Catatan'] ?? null),
            totalPesanan: Uang::Dari((string) $valid['TotalPesanan']),
            baris: $baris,
            pembayaran: array_values(array_map(fn (array $p): DataPembayaranPenjualanPos => new DataPembayaranPenjualanPos(
                strtoupper((string) $p['Uuid']),
                strtoupper((string) $p['UuidMetodePembayaran']),
                Uang::Dari((string) $p['Jumlah']),
                self::AmbilTeks($p['Referensi'] ?? null),
            ), (array) $valid['Pembayaran'])),
        ));
    }

    private static function AmbilTeks(mixed $nilai): ?string
    {
        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }
}
